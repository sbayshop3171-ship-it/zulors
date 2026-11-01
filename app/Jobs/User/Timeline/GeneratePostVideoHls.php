<?php

namespace App\Jobs\User\Timeline;

use App\Enums\Media\MediaStatus;
use App\Enums\Media\MediaType;
use App\Events\User\Timeline\MediaUpdatedEvent;
use App\Models\Media;
use App\Models\Post;
use App\Services\Filesystem\Upload\VideoUploadService;
use App\Services\Media\ReelsHlsRollout;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Symfony\Component\Process\ExecutableFinder;
use Symfony\Component\Process\Process;
use Throwable;

class GeneratePostVideoHls implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $tries = 5;
    public int $timeout = 21600;
    // Keep this shorter than the stale-job threshold so a recovered dispatch can acquire the unique lock.
    public int $uniqueFor = 5400;

    private const LADDER = [
        240 => ['video' => 350000, 'max' => 450000, 'bandwidth' => 520000],
        360 => ['video' => 650000, 'max' => 800000, 'bandwidth' => 900000],
        480 => ['video' => 1000000, 'max' => 1250000, 'bandwidth' => 1400000],
        720 => ['video' => 2100000, 'max' => 2500000, 'bandwidth' => 2700000],
    ];

    public function __construct(public readonly int $mediaId)
    {
        $this->onConnection(config('media.queue_connection'));
        $this->onQueue(config('media.queues.video'));
        $this->timeout = max(1800, (int) config('media.reels_hls.timeout_seconds', 21600));
    }

    public function uniqueId(): string
    {
        return 'post-video-hls:'.$this->mediaId;
    }

    public function backoff(): array
    {
        return [30, 120, 600, 1800];
    }

    public function handle(VideoUploadService $videoUploadService, ReelsHlsRollout $rollout): void
    {
        $media = Media::query()->with('mediaable')->find($this->mediaId);

        if (! $media || ! $media->type->isVideo() || ! $media->status->isProcessed()
            || ! ($media->mediaable instanceof Post) || ! $rollout->processingEnabled()
            || data_get($media->metadata, 'provider') !== 'r2') {
            return;
        }

        $metadata = $media->metadata ?? [];
        $sourceSignature = $this->sourceSignature($media);
        $attempt = (int) data_get($metadata, 'background_processing_attempts', 0) + 1;

        if (data_get($metadata, 'playback.hls_state') === 'ready'
            && data_get($metadata, 'playback.hls_source_signature') === $sourceSignature) {
            return;
        }

        $startedAt = microtime(true);
        $queueAgeSeconds = $this->queueAgeSeconds($metadata);
        $workDirectory = storage_path('app/tmp/reel-hls/'.$media->id.'-'.bin2hex(random_bytes(5)));
        $sourcePath = $workDirectory.'/source.'.($this->safeExtension($media->extension) ?: 'mp4');
        @mkdir($workDirectory, 0775, true);

        try {
            $this->updateMetadata($media, function (array $next) use ($sourceSignature, $attempt): array {
                $next['playback_state'] = 'ready';
                $next['background_processing_state'] = 'processing';
                $next['background_processing_progress'] = 1;
                $next['background_processing_updated_at'] = now()->toIso8601String();
                $next['background_processing_attempts'] = $attempt;
                $next['playback'] = array_merge((array) ($next['playback'] ?? []), [
                    'hls' => null,
                    'hls_state' => 'processing',
                    'hls_source_signature' => $sourceSignature,
                    'renditions' => [],
                ]);

                return $next;
            });

            Log::channel('performance')->info('reel_hls_job_started', [
                'queue_age_seconds' => $queueAgeSeconds,
            ]);

            $this->downloadSource($media, $sourcePath);
            $dimensions = $videoUploadService->getVideoDimensions($sourcePath);
            $sourceHeight = (int) ($dimensions['height'] ?? 0);
            $sourceWidth = (int) ($dimensions['width'] ?? 0);
            $targets = array_filter(array_keys(self::LADDER), static fn (int $height): bool => $height <= $sourceHeight);

            if (! $targets || $sourceHeight < 240 || $sourceWidth < 2) {
                $this->markSkipped($media, $sourceSignature);

                return;
            }

            $ffmpeg = $this->ffmpegBinary();
            $renditions = [];
            $playlistEntries = [];
            $diskName = (string) $media->disk;
            $outputPrefix = 'uploads/posts/videos/'.$media->id.'/hls/'.$sourceSignature;
            $outputDisk = Storage::disk($diskName);
            $progressBase = 5;
            $progressPerRendition = (int) floor(75 / count($targets));

            foreach ($targets as $height) {
                $height = (int) $height;
                $profile = self::LADDER[$height];
                $renditionDirectory = $workDirectory.'/v'.$height;
                @mkdir($renditionDirectory, 0775, true);
                $playlistPath = $renditionDirectory.'/index.m3u8';

                $this->runRendition($ffmpeg, $sourcePath, $playlistPath, $profile, $height, $workDirectory, $media);
                $this->uploadDirectory($outputDisk, $renditionDirectory, $outputPrefix.'/v'.$height);

                $width = max(2, (int) (2 * round(($sourceWidth * $height / max(1, $sourceHeight)) / 2)));
                $renditions[] = [
                    'height' => $height,
                    'width' => $width,
                    'state' => 'ready',
                    'bandwidth' => $profile['bandwidth'],
                ];
                $playlistEntries[] = "#EXT-X-STREAM-INF:BANDWIDTH={$profile['bandwidth']},RESOLUTION={$width}x{$height}\nv{$height}/index.m3u8";

                $progressBase += $progressPerRendition;
                $this->updateMetadata($media, function (array $next) use ($progressBase, $renditions, $sourceSignature): array {
                    $next['background_processing_progress'] = min(95, $progressBase);
                    $next['background_processing_updated_at'] = now()->toIso8601String();
                    $next['playback'] = array_merge((array) ($next['playback'] ?? []), [
                        'hls' => null,
                        'hls_state' => 'processing',
                        'hls_source_signature' => $sourceSignature,
                        'renditions' => $renditions,
                    ]);

                    return $next;
                });
            }

            $masterPath = $workDirectory.'/master.m3u8';
            file_put_contents($masterPath, "#EXTM3U\n#EXT-X-VERSION:3\n#EXT-X-INDEPENDENT-SEGMENTS\n".implode("\n", $playlistEntries)."\n");
            $this->putFile($outputDisk, $outputPrefix.'/master.m3u8', $masterPath, 'application/vnd.apple.mpegurl');

            $this->updateMetadata($media, function (array $next) use ($diskName, $outputPrefix, $renditions, $sourceSignature): array {
                $next['playback_state'] = 'ready';
                $next['background_processing_state'] = 'processed';
                $next['background_processing_progress'] = 100;
                $next['background_processing_updated_at'] = now()->toIso8601String();
                $next['background_processing_error'] = null;
                $next['playback'] = array_merge((array) ($next['playback'] ?? []), [
                    'hls' => storage_url($outputPrefix.'/master.m3u8', $diskName),
                    'hls_state' => 'ready',
                    'hls_source_signature' => $sourceSignature,
                    'renditions' => $renditions,
                ]);

                return $next;
            });

            Log::channel('performance')->info('reel_hls_job_completed', [
                'queue_age_seconds' => $queueAgeSeconds,
                'encoding_duration_seconds' => round(microtime(true) - $startedAt, 3),
                'rendition_count' => count($renditions),
                'outcome' => 'ready',
            ]);
        } catch (Throwable $exception) {
            Log::warning('Reel HLS generation attempt failed.', [
                'media_id' => $media->id,
                'exception' => $exception::class,
            ]);

            throw $exception;
        } finally {
            $this->removeDirectory($workDirectory);
        }
    }

    public function failed(Throwable $exception): void
    {
        $media = Media::query()->with('mediaable')->find($this->mediaId);

        if (! $media) {
            return;
        }

        $this->updateMetadata($media, static function (array $metadata) use ($exception): array {
            $metadata['playback_state'] = 'ready';
            $metadata['background_processing_state'] = 'failed';
            $metadata['background_processing_updated_at'] = now()->toIso8601String();
            $metadata['background_processing_error'] = 'HLS rendition generation failed.';
            $metadata['playback'] = array_merge((array) ($metadata['playback'] ?? []), [
                'hls' => null,
                'hls_state' => 'failed',
            ]);

            return $metadata;
        });

        Log::channel('performance')->warning('reel_hls_job_failed', [
            'outcome' => 'failed',
            'exception' => $exception::class,
        ]);
    }

    private function runRendition(string $ffmpeg, string $sourcePath, string $playlistPath, array $profile, int $height, string $workingDirectory, Media $media): void
    {
        $segmentPattern = dirname($playlistPath).'/segment_%05d.ts';
        $command = [
            $ffmpeg, '-hide_banner', '-loglevel', 'error', '-y', '-i', $sourcePath,
            '-map', '0:v:0', '-map', '0:a:0?',
            '-vf', 'scale=-2:'.$height,
            '-r', '30', '-c:v', 'libx264', '-preset', 'veryfast', '-profile:v', 'baseline',
            '-pix_fmt', 'yuv420p', '-b:v', (string) $profile['video'], '-maxrate', (string) $profile['max'],
            '-bufsize', (string) ($profile['max'] * 2), '-g', '120', '-keyint_min', '120', '-sc_threshold', '0',
            '-c:a', 'aac', '-b:a', '96k', '-ac', '2',
            '-progress', 'pipe:1', '-nostats', '-f', 'hls', '-hls_time', '4', '-hls_playlist_type', 'vod', '-hls_segment_type', 'mpegts',
            '-hls_flags', 'independent_segments', '-hls_segment_filename', $segmentPattern, $playlistPath,
        ];

        $process = new Process($command, $workingDirectory);
        $process->setTimeout((float) config('media.reels_hls.timeout_seconds', 21600));
        $lastHeartbeat = time();
        $process->mustRun(function (string $type, string $buffer) use ($media, &$lastHeartbeat): void {
            if ($type === Process::OUT && time() - $lastHeartbeat >= 60) {
                $lastHeartbeat = time();
                $this->updateMetadata($media, static function (array $metadata): array {
                    $metadata['background_processing_updated_at'] = now()->toIso8601String();

                    return $metadata;
                }, false);
            }
        });

        if (! is_file($playlistPath) || ! str_contains((string) file_get_contents($playlistPath), '#EXTINF:')) {
            throw new RuntimeException('FFmpeg did not produce a playable HLS rendition.');
        }
    }

    private function downloadSource(Media $media, string $destination): void
    {
        $stream = Storage::disk($media->disk)->readStream($media->source_path);

        if (! is_resource($stream)) {
            throw new RuntimeException('Unable to read the playback-ready video source.');
        }

        try {
            $target = fopen($destination, 'wb');
            if (! is_resource($target)) {
                throw new RuntimeException('Unable to create a temporary HLS source file.');
            }
            stream_copy_to_stream($stream, $target);
            fclose($target);
        } finally {
            fclose($stream);
        }
    }

    private function uploadDirectory($disk, string $localDirectory, string $remoteDirectory): void
    {
        $files = glob($localDirectory.'/*') ?: [];
        sort($files);

        foreach ($files as $file) {
            if (! is_file($file)) {
                continue;
            }

            $contentType = str_ends_with($file, '.m3u8')
                ? 'application/vnd.apple.mpegurl'
                : 'video/mp2t';
            $this->putFile($disk, $remoteDirectory.'/'.basename($file), $file, $contentType);
        }
    }

    private function putFile($disk, string $path, string $localPath, string $contentType): void
    {
        $stream = fopen($localPath, 'rb');
        if (! is_resource($stream)) {
            throw new RuntimeException('Unable to open an HLS output file.');
        }

        try {
            $saved = $disk->put($path, $stream, [
                'visibility' => 'public',
                'ContentType' => $contentType,
                'CacheControl' => config('media.cache.control', 'public, max-age=31536000, immutable'),
            ]);
        } finally {
            fclose($stream);
        }

        if (! $saved) {
            throw new RuntimeException('Unable to publish an HLS output file.');
        }
    }

    private function ffmpegBinary(): string
    {
        $configured = trim((string) config('ffmpeg.ffmpeg_path'));
        if ($configured !== '' && is_executable($configured)) {
            return $configured;
        }

        $binary = (new ExecutableFinder())->find('ffmpeg');
        if (! $binary) {
            throw new RuntimeException('FFmpeg binary is unavailable.');
        }

        return $binary;
    }

    private function sourceSignature(Media $media): string
    {
        return substr(hash('sha256', implode('|', [
            (string) $media->disk,
            (string) $media->source_path,
            (string) $media->size,
            (string) $media->mime,
            (string) $media->extension,
        ])), 0, 24);
    }

    private function queueAgeSeconds(array $metadata): float
    {
        $queuedAt = data_get($metadata, 'background_processing_dispatched_at');
        if (! $queuedAt) {
            return 0;
        }

        try {
            return max(0, now()->diffInSeconds($queuedAt, true));
        } catch (Throwable) {
            return 0;
        }
    }

    private function safeExtension(?string $extension): string
    {
        $extension = strtolower((string) $extension);

        return preg_match('/\A[a-z0-9]{2,8}\z/', $extension) ? $extension : 'mp4';
    }

    private function updateMetadata(Media $media, callable $mutator, bool $broadcast = true): void
    {
        $media->refresh();
        $media->metadata = $mutator($media->metadata ?? []);
        $media->save();

        if ($broadcast && $media->mediaable instanceof Post && $media->mediaable->user_id) {
            event(new MediaUpdatedEvent($media->refresh(), (int) $media->mediaable->user_id));
        }
    }

    private function markSkipped(Media $media, string $sourceSignature): void
    {
        $this->updateMetadata($media, static function (array $metadata) use ($sourceSignature): array {
            $metadata['playback_state'] = 'ready';
            $metadata['background_processing_state'] = 'skipped';
            $metadata['background_processing_progress'] = 100;
            $metadata['background_processing_updated_at'] = now()->toIso8601String();
            $metadata['playback'] = array_merge((array) ($metadata['playback'] ?? []), [
                'hls' => null,
                'hls_state' => 'skipped',
                'hls_source_signature' => $sourceSignature,
                'renditions' => [],
            ]);

            return $metadata;
        });
    }

    private function removeDirectory(string $directory): void
    {
        if (! is_dir($directory)) {
            return;
        }

        foreach (new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($directory, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        ) as $entry) {
            $entry->isDir() ? @rmdir($entry->getPathname()) : @unlink($entry->getPathname());
        }
        @rmdir($directory);
    }
}
