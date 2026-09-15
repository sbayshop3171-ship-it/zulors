<?php

namespace App\Support\StoryMusic;

use App\Services\Filesystem\Upload\AudioUploadService;
use Illuminate\Support\Str;
use Symfony\Component\Process\Process;

class OriginalAudioAnalyzer
{
    public function analyze(string $localAudioPath): array
    {
        if(! $this->isEnabled()) {
            return [
                'duration_seconds' => 0,
                'sample_rate' => 0,
                'channels' => 0,
                'bit_rate' => 0,
                'mean_volume_db' => null,
                'max_volume_db' => null,
                'silence_ratio' => 0.0,
                'silence_seconds' => 0.0,
                'score' => 100,
                'is_music_candidate' => true,
                'reject_reason' => null,
                'analysis_disabled' => true,
            ];
        }

        $absolutePath = storage_local_path($localAudioPath);
        $audioService = app(AudioUploadService::class);
        $ffprobe = $audioService->getFFProbe();
        $format = $ffprobe->format($absolutePath);
        $stream = $ffprobe->streams($absolutePath)->audios()->first();

        $duration = max(0.0, (float) $format->get('duration', 0));
        $sampleRate = max(0, (int) ($stream?->get('sample_rate') ?: 0));
        $channels = max(0, (int) ($stream?->get('channels') ?: 0));
        $bitRate = max(0, (int) ($stream?->get('bit_rate') ?: $format->get('bit_rate', 0)));
        $volume = $this->volumeStats($absolutePath);
        $silence = $this->silenceStats($absolutePath, $duration);

        $analysis = [
            'duration_seconds' => (int) ceil($duration),
            'sample_rate' => $sampleRate,
            'channels' => $channels,
            'bit_rate' => $bitRate,
            'mean_volume_db' => $volume['mean_volume_db'],
            'max_volume_db' => $volume['max_volume_db'],
            'silence_ratio' => $silence['silence_ratio'],
            'silence_seconds' => $silence['silence_seconds'],
        ];

        $analysis['score'] = $this->score($analysis);
        $analysis['is_music_candidate'] = $this->isPublishable($analysis);
        $analysis['reject_reason'] = $analysis['is_music_candidate'] ? null : $this->rejectReason($analysis);

        return $analysis;
    }

    public function isEnabled(): bool
    {
        return (bool) config('story_music.original_audio.analysis.enabled', true);
    }

    public function isPublishable(array $analysis): bool
    {
        if(! $this->isEnabled()) {
            return true;
        }

        return (int) ($analysis['score'] ?? 0) >= (int) config('story_music.original_audio.analysis.min_score', 55)
            && (int) ($analysis['sample_rate'] ?? 0) >= (int) config('story_music.original_audio.analysis.min_sample_rate', 16000)
            && (int) ($analysis['channels'] ?? 0) >= 1
            && (float) ($analysis['silence_ratio'] ?? 1.0) <= (float) config('story_music.original_audio.analysis.max_silence_ratio', 0.65)
            && (float) ($analysis['max_volume_db'] ?? -120.0) >= (float) config('story_music.original_audio.analysis.min_max_volume_db', -38)
            && (float) ($analysis['mean_volume_db'] ?? -120.0) >= (float) config('story_music.original_audio.analysis.min_mean_volume_db', -45);
    }

    private function score(array $analysis): int
    {
        $score = 0;

        $duration = (int) ($analysis['duration_seconds'] ?? 0);
        $sampleRate = (int) ($analysis['sample_rate'] ?? 0);
        $bitRate = (int) ($analysis['bit_rate'] ?? 0);
        $channels = (int) ($analysis['channels'] ?? 0);
        $meanVolume = (float) ($analysis['mean_volume_db'] ?? -120);
        $maxVolume = (float) ($analysis['max_volume_db'] ?? -120);
        $silenceRatio = (float) ($analysis['silence_ratio'] ?? 1);

        if($duration >= (int) config('story_music.original_audio.min_duration_seconds', 3)) {
            $score += 15;
        }

        if($sampleRate >= 44100) {
            $score += 15;
        }
        elseif($sampleRate >= 22050) {
            $score += 10;
        }

        if($bitRate >= 96000) {
            $score += 15;
        }
        elseif($bitRate >= 48000) {
            $score += 10;
        }
        elseif($bitRate >= 24000) {
            $score += 5;
        }

        if($channels >= 2) {
            $score += 10;
        }
        elseif($channels === 1) {
            $score += 5;
        }

        if($meanVolume >= -28 && $meanVolume <= -6) {
            $score += 20;
        }
        elseif($meanVolume >= -42) {
            $score += 10;
        }

        if($maxVolume >= -18) {
            $score += 15;
        }
        elseif($maxVolume >= -35) {
            $score += 8;
        }

        if($silenceRatio <= 0.12) {
            $score += 15;
        }
        elseif($silenceRatio <= 0.35) {
            $score += 10;
        }
        elseif($silenceRatio <= 0.65) {
            $score += 5;
        }

        return max(0, min(100, $score));
    }

    private function rejectReason(array $analysis): string
    {
        if((int) ($analysis['sample_rate'] ?? 0) < (int) config('story_music.original_audio.analysis.min_sample_rate', 16000)) {
            return 'sample_rate_too_low';
        }

        if((int) ($analysis['channels'] ?? 0) < 1) {
            return 'no_audio_channels';
        }

        if((float) ($analysis['silence_ratio'] ?? 1.0) > (float) config('story_music.original_audio.analysis.max_silence_ratio', 0.65)) {
            return 'too_much_silence';
        }

        if((float) ($analysis['max_volume_db'] ?? -120.0) < (float) config('story_music.original_audio.analysis.min_max_volume_db', -38)) {
            return 'volume_too_low';
        }

        if((float) ($analysis['mean_volume_db'] ?? -120.0) < (float) config('story_music.original_audio.analysis.min_mean_volume_db', -45)) {
            return 'average_volume_too_low';
        }

        return 'score_too_low';
    }

    private function volumeStats(string $absolutePath): array
    {
        $output = $this->runFfmpegFilter($absolutePath, 'volumedetect');

        return [
            'mean_volume_db' => $this->parseDbValue($output, '/mean_volume:\s*(-?\d+(?:\.\d+)?)\s*dB/i', -120.0),
            'max_volume_db' => $this->parseDbValue($output, '/max_volume:\s*(-?\d+(?:\.\d+)?)\s*dB/i', -120.0),
        ];
    }

    private function silenceStats(string $absolutePath, float $duration): array
    {
        if($duration <= 0) {
            return [
                'silence_ratio' => 1.0,
                'silence_seconds' => 0.0,
            ];
        }

        $output = $this->runFfmpegFilter($absolutePath, 'silencedetect=noise=' . config('story_music.original_audio.analysis.silence_noise_db', '-45dB') . ':d=0.7');
        preg_match_all('/silence_duration:\s*(\d+(?:\.\d+)?)/i', $output, $matches);

        $silenceSeconds = collect($matches[1] ?? [])
            ->map(fn (string $value) => (float) $value)
            ->sum();

        return [
            'silence_ratio' => round(max(0.0, min(1.0, $silenceSeconds / $duration)), 4),
            'silence_seconds' => round($silenceSeconds, 3),
        ];
    }

    private function runFfmpegFilter(string $absolutePath, string $filter): string
    {
        $process = new Process([
            $this->ffmpegBinary(),
            '-hide_banner',
            '-nostats',
            '-i',
            $absolutePath,
            '-af',
            $filter,
            '-f',
            'null',
            '-',
        ]);

        $process->setTimeout((int) config('story_music.original_audio.analysis.ffmpeg_timeout', 90));
        $process->run();

        return $process->getOutput() . "\n" . $process->getErrorOutput();
    }

    private function ffmpegBinary(): string
    {
        $configured = trim((string) config('ffmpeg.ffmpeg_path'));

        if($configured && is_executable($configured)) {
            return $configured;
        }

        $fromPath = trim((string) shell_exec('command -v ffmpeg 2>/dev/null'));

        return $fromPath ?: 'ffmpeg';
    }

    private function parseDbValue(string $output, string $pattern, float $fallback): float
    {
        if(! Str::contains($output, 'volume')) {
            return $fallback;
        }

        if(preg_match($pattern, $output, $matches)) {
            return (float) $matches[1];
        }

        return $fallback;
    }
}
