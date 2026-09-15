<?php

namespace App\Jobs\User\Story;

use App\Models\Media;
use App\Models\Post;
use App\Models\StoryFrame;
use App\Models\StoryMusicTrack;
use App\Models\User;
use App\Services\Filesystem\Upload\VideoUploadService;
use App\Support\StoryMusic\OriginalAudioAnalyzer;
use App\Support\StoryMusic\OriginalAudioEligibility;
use App\Support\StoryMusic\StoryMusicSearchIndex;
use FFMpeg\Coordinate\TimeCode;
use FFMpeg\Format\Audio\Mp3;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ExtractOriginalAudioFromMedia implements ShouldQueue
{
    use Queueable;

    public $timeout = 60 * 30;

    public function __construct(
        private readonly int $mediaId,
        private readonly bool $force = false,
        private readonly bool $ignoreConsent = false,
        private readonly ?bool $publish = null,
        private readonly bool $ignoreCategory = false
    ) {
        $this->onConnection(config('media.queue_connection'));
    }

    public function handle(): void
    {
        if(! (bool) config('story_music.original_audio.enabled', true)) {
            return;
        }

        $media = Media::query()->with('mediaable')->find($this->mediaId);

        if(empty($media) || ! $this->canExtract($media)) {
            return;
        }

        if(! $this->force && StoryMusicTrack::where('source_media_id', $media->id)->where('source', 'user_upload')->exists()) {
            return;
        }

        $owner = $this->mediaOwner($media);

        if(empty($owner)) {
            return;
        }

        $videoUploadService = app(VideoUploadService::class);
        $localVideoPath = null;
        $localAudioPath = null;
        $downloadedRemote = false;

        try {
            $localVideoPath = $this->prepareLocalVideo($media, $videoUploadService);
            $downloadedRemote = $media->disk !== 'local';

            if(! $this->hasAudioStream($videoUploadService, $localVideoPath)) {
                $this->markMediaExtraction($media, 'skipped_no_audio');
                return;
            }

            $localAudioPath = $this->extractMp3($videoUploadService, $localVideoPath, $media);
            $durationSeconds = app(\App\Services\Filesystem\Upload\AudioUploadService::class)
                ->getAudioDurationSeconds($localAudioPath);

            if(! $this->durationAllowed($durationSeconds)) {
                $this->markMediaExtraction($media, 'skipped_duration');
                return;
            }

            $analysis = app(OriginalAudioAnalyzer::class)->analyze($localAudioPath);

            if(! (bool) ($analysis['is_music_candidate'] ?? false)) {
                $this->markMediaExtraction($media, 'skipped_audio_analysis', [
                    'audio_analysis' => $analysis,
                    'skip_reason' => $analysis['reject_reason'] ?? 'audio_not_music_candidate',
                ]);
                return;
            }

            $track = $this->storeTrack($media, $owner, $localAudioPath, $durationSeconds, $analysis);

            $this->markMediaExtraction($media, 'processed', [
                'track_id' => $track->id,
                'extracted_at' => now()->toIso8601String(),
                'audio_analysis' => $analysis,
            ]);
        }
        catch (\Throwable $e) {
            $this->markMediaExtraction($media, 'failed', [
                'error' => Str::limit($e->getMessage(), 240),
            ]);

            Log::warning('Original audio extraction failed.', [
                'media_id' => $media->id,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
        finally {
            if($downloadedRemote && $localVideoPath) {
                Storage::disk('local')->delete($localVideoPath);
            }

            if($localAudioPath) {
                Storage::disk('local')->delete($localAudioPath);
            }
        }
    }

    public function tries(): int
    {
        return 3;
    }

    public function middleware(): array
    {
        return [(new \Illuminate\Queue\Middleware\WithoutOverlapping('original-audio-media:' . $this->mediaId))
            ->releaseAfter(60)->expireAfter($this->timeout + 60)];
    }

    public function backoff(): array
    {
        return [60, 180, 600];
    }

    private function canExtract(Media $media): bool
    {
        if(! $media->type?->isVideo() || ! $media->status?->isProcessed()) {
            return false;
        }

        if($media->disk === 'cloudflare_stream') {
            return false;
        }

        if((bool) config('story_music.original_audio.require_consent', true) && ! $this->ignoreConsent) {
            if(! (bool) data_get($media->metadata, 'story_music.allow_reuse', false)) {
                return false;
            }
        }

        if(! $this->ignoreCategory && ! OriginalAudioEligibility::shouldAutoExtract($media)) {
            return false;
        }

        return true;
    }

    private function mediaOwner(Media $media): ?User
    {
        $mediaable = $media->mediaable;

        if($mediaable instanceof Post) {
            return $mediaable->user()->first();
        }

        if($mediaable instanceof StoryFrame) {
            return $mediaable->story?->user()->first();
        }

        return null;
    }

    private function prepareLocalVideo(Media $media, VideoUploadService $videoUploadService): string
    {
        if($media->disk === 'local') {
            return $media->source_path;
        }

        $localPath = $videoUploadService->generateVideoTemporaryFilePath($media->extension ?: 'mp4');

        Storage::disk('local')->makeDirectory(dirname($localPath));

        $readStream = Storage::disk($media->disk)->readStream($media->source_path);

        if(! is_resource($readStream)) {
            throw new \RuntimeException('Unable to read source video stream.');
        }

        $writeStream = fopen(storage_local_path($localPath), 'w+b');

        if(! is_resource($writeStream)) {
            fclose($readStream);
            throw new \RuntimeException('Unable to create local video file.');
        }

        stream_copy_to_stream($readStream, $writeStream);

        fclose($readStream);
        fclose($writeStream);

        return $localPath;
    }

    private function hasAudioStream(VideoUploadService $videoUploadService, string $localVideoPath): bool
    {
        $absolutePath = storage_local_path($localVideoPath);

        return filled($videoUploadService->getFFProbe()->streams($absolutePath)->audios()->first());
    }

    private function extractMp3(VideoUploadService $videoUploadService, string $localVideoPath, Media $media): string
    {
        $audioPath = 'tmp/audios/' . Str::uuid() . '.mp3';
        $absoluteAudioPath = storage_local_path($audioPath);

        Storage::disk('local')->makeDirectory(dirname($audioPath));

        $format = new Mp3();
        $format->setAudioKiloBitrate(max(32, (int) config('story_music.original_audio.bitrate', 96)));

        $video = $videoUploadService->getFFMpeg()->open(storage_local_path($localVideoPath));
        $video->filters()->clip(TimeCode::fromSeconds(0), TimeCode::fromSeconds($this->audioClipSeconds($media)));
        $video->save($format, $absoluteAudioPath);

        return $audioPath;
    }

    private function audioClipSeconds(Media $media): int
    {
        $max = max(1, (int) config('story_music.original_audio.max_duration_seconds', 60));
        $duration = (int) ceil((float) data_get($media->metadata, 'duration_seconds', data_get($media->metadata, 'original_duration_seconds', 0)));

        if($duration < 1) {
            return $max;
        }

        return max(1, min($duration, $max));
    }

    private function durationAllowed(int $durationSeconds): bool
    {
        $min = max(1, (int) config('story_music.original_audio.min_duration_seconds', 3));

        return $durationSeconds >= $min;
    }

    private function storeTrack(Media $media, User $owner, string $localAudioPath, int $durationSeconds, array $analysis): StoryMusicTrack
    {
        $disk = (string) config('story_music.disk', 'r2_music');
        $basePath = trim(config('story_music.prefix'), '/') . '/original-audio/user-' . $owner->id . '/media-' . $media->id;
        $remoteAudioPath = "{$basePath}/audio.mp3";
        $sourceUrl = $this->sourceUrl($media);
        $metadata = $media->metadata ?? [];
        $recognition = $this->recognitionMetadata($metadata);
        $title = trim((string) (data_get($metadata, 'story_music.title') ?: data_get($recognition, 'title')));
        $artist = trim((string) (data_get($metadata, 'story_music.artist') ?: data_get($recognition, 'artist') ?: $owner->username ?: $owner->name));
        $isActive = $this->shouldPublish();
        $category = OriginalAudioEligibility::categoryFor($media) ?: 'original_audio';
        $expiresAt = $this->expiresAt();
        $lyricsKeywords = StoryMusicSearchIndex::keywords(data_get($metadata, 'story_music.lyrics_keywords'));
        $searchKeywords = StoryMusicSearchIndex::keywords(data_get($metadata, 'story_music.search_keywords'));
        $sourceCaption = $this->sourceCaption($media);

        if(blank($title)) {
            $title = 'Original audio';
        }

        $audioStream = fopen(storage_local_path($localAudioPath), 'rb');

        if(! is_resource($audioStream)) {
            throw new \RuntimeException('Unable to open extracted audio file.');
        }

        try {
            Storage::disk($disk)->put($remoteAudioPath, $audioStream, [
                'visibility' => 'private',
                'ContentType' => 'audio/mpeg',
            ]);
        }
        finally {
            fclose($audioStream);
        }

        return StoryMusicTrack::updateOrCreate(
            [
                'source' => 'user_upload',
                'source_media_id' => $media->id,
            ],
            [
                'user_id' => $owner->id,
                'source_media_type' => $media->mediaable_type,
                'slug' => 'original-audio-' . $media->id,
                'title' => $title,
                'artist' => $artist ? "@{$artist}" : null,
                'source_url' => $sourceUrl,
                'license_url' => (string) config('story_music.original_audio.license_url'),
                'license_type' => 'ugc-original',
                'disk' => $disk,
                'audio_path' => $remoteAudioPath,
                'cover_path' => null,
                'duration_seconds' => $durationSeconds,
                'audio_quality_score' => (int) ($analysis['score'] ?? 0),
                'mood' => data_get($metadata, 'story_music.mood'),
                'genre' => data_get($metadata, 'story_music.genre'),
                'collection' => 'original_audio',
                'tags' => array_values(array_filter([
                    'original',
                    'user-audio',
                    $category,
                    data_get($metadata, 'story_music.mood'),
                    data_get($metadata, 'story_music.genre'),
                    data_get($metadata, 'story_music.album'),
                    ...$searchKeywords,
                ])),
                'search_text' => StoryMusicSearchIndex::build([
                    $title,
                    $artist,
                    data_get($metadata, 'story_music.album'),
                    data_get($metadata, 'story_music.mood'),
                    data_get($metadata, 'story_music.genre'),
                    $category,
                    $sourceCaption,
                    data_get($recognition, 'title'),
                    data_get($recognition, 'artist'),
                    data_get($recognition, 'album'),
                    $lyricsKeywords,
                    $searchKeywords,
                ]),
                'meta' => [
                    'source_media_id' => $media->id,
                    'source_media_type' => $media->mediaable_type,
                    'source_disk' => $media->disk,
                    'creator_user_id' => $owner->id,
                    'category' => $category,
                    'album' => data_get($metadata, 'story_music.album'),
                    'lyrics_keywords' => $lyricsKeywords,
                    'search_keywords' => $searchKeywords,
                    'recognition' => $recognition,
                    'audio_analysis' => $analysis,
                    'source_caption' => $sourceCaption,
                    'consent_recorded_at' => data_get($metadata, 'story_music.consent_recorded_at'),
                    'auto_extracted' => true,
                ],
                'review_status' => $isActive ? 'approved' : 'pending',
                'recognition_status' => $recognition['status'],
                'recognition_provider' => $recognition['provider'],
                'published_at' => $isActive ? now() : null,
                'expires_at' => $expiresAt,
                'is_active' => $isActive,
            ]
        );
    }

    private function shouldPublish(): bool
    {
        if($this->publish !== null) {
            return $this->publish;
        }

        return (bool) config('story_music.original_audio.auto_publish', true);
    }

    private function expiresAt(): ?\Illuminate\Support\Carbon
    {
        $hours = (int) config('story_music.original_audio.expire_after_hours', 24);

        return $hours > 0 ? now()->addHours($hours) : null;
    }

    private function sourceUrl(Media $media): string
    {
        $mediaable = $media->mediaable;

        if($mediaable instanceof Post) {
            return $mediaable->url;
        }

        if($mediaable instanceof StoryFrame) {
            return $mediaable->story?->url ?: url('/stories');
        }

        return url('/');
    }

    private function sourceCaption(Media $media): ?string
    {
        $mediaable = $media->mediaable;

        if($mediaable instanceof Post) {
            return (string) ($mediaable->caption ?? '');
        }

        if($mediaable instanceof StoryFrame) {
            return (string) data_get($mediaable->meta ?? [], 'caption', '');
        }

        return null;
    }

    private function recognitionMetadata(array $metadata): array
    {
        $confidence = data_get($metadata, 'story_music.recognition_confidence');
        $confidence = is_numeric($confidence) ? (float) $confidence : null;
        $recognizedTitle = trim((string) data_get($metadata, 'story_music.recognized_title'));
        $recognizedArtist = trim((string) data_get($metadata, 'story_music.recognized_artist'));
        $provider = trim((string) data_get($metadata, 'story_music.recognition_provider'));
        $minConfidence = (float) config('story_music.original_audio.recognition.min_confidence', 70);
        $matched = filled($recognizedTitle) || filled($recognizedArtist);

        return [
            'status' => $matched && ($confidence === null || $confidence >= $minConfidence) ? 'matched' : 'not_configured',
            'provider' => filled($provider) ? $provider : config('story_music.original_audio.recognition.provider'),
            'confidence' => $confidence,
            'title' => $recognizedTitle ?: null,
            'artist' => $recognizedArtist ?: null,
            'album' => data_get($metadata, 'story_music.album'),
        ];
    }

    private function markMediaExtraction(Media $media, string $status, array $extra = []): void
    {
        $metadata = $media->metadata ?? [];
        $metadata['story_music'] = array_merge((array) data_get($metadata, 'story_music', []), [
            'original_audio_status' => $status,
            'original_audio_updated_at' => now()->toIso8601String(),
            'category' => OriginalAudioEligibility::categoryFor($media),
        ], $extra);

        $media->metadata = $metadata;
        $media->save();
    }
}
