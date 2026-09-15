<?php

namespace App\Jobs\User\Story;

use App\Models\Media;
use App\Models\Post;
use App\Models\StoryFrame;
use App\Models\StoryMusicTrack;
use App\Models\User;
use App\Services\Filesystem\Upload\VideoUploadService;
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
        private readonly ?bool $publish = null
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

            $localAudioPath = $this->extractMp3($videoUploadService, $localVideoPath);
            $durationSeconds = app(\App\Services\Filesystem\Upload\AudioUploadService::class)
                ->getAudioDurationSeconds($localAudioPath);

            if(! $this->durationAllowed($durationSeconds)) {
                $this->markMediaExtraction($media, 'skipped_duration');
                return;
            }

            $track = $this->storeTrack($media, $owner, $localAudioPath, $durationSeconds);

            $this->markMediaExtraction($media, 'processed', [
                'track_id' => $track->id,
                'extracted_at' => now()->toIso8601String(),
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
            return (bool) data_get($media->metadata, 'story_music.allow_reuse', false);
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

    private function extractMp3(VideoUploadService $videoUploadService, string $localVideoPath): string
    {
        $audioPath = 'tmp/audios/' . Str::uuid() . '.mp3';
        $absoluteAudioPath = storage_local_path($audioPath);

        Storage::disk('local')->makeDirectory(dirname($audioPath));

        $format = new Mp3();
        $format->setAudioKiloBitrate(max(32, (int) config('story_music.original_audio.bitrate', 96)));

        $videoUploadService->getFFMpeg()
            ->open(storage_local_path($localVideoPath))
            ->save($format, $absoluteAudioPath);

        return $audioPath;
    }

    private function durationAllowed(int $durationSeconds): bool
    {
        $min = max(1, (int) config('story_music.original_audio.min_duration_seconds', 3));
        $max = max($min, (int) config('story_music.original_audio.max_duration_seconds', 180));

        return $durationSeconds >= $min && $durationSeconds <= $max;
    }

    private function storeTrack(Media $media, User $owner, string $localAudioPath, int $durationSeconds): StoryMusicTrack
    {
        $disk = (string) config('story_music.disk', 'r2_music');
        $basePath = trim(config('story_music.prefix'), '/') . '/original-audio/user-' . $owner->id . '/media-' . $media->id;
        $remoteAudioPath = "{$basePath}/audio.mp3";
        $sourceUrl = $this->sourceUrl($media);
        $metadata = $media->metadata ?? [];
        $title = trim((string) data_get($metadata, 'story_music.title'));
        $artist = trim((string) ($owner->username ?: $owner->name));
        $isActive = $this->shouldPublish();

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
                'mood' => data_get($metadata, 'story_music.mood'),
                'genre' => data_get($metadata, 'story_music.genre'),
                'collection' => 'original_audio',
                'tags' => array_values(array_filter([
                    'original',
                    'user-audio',
                    data_get($metadata, 'story_music.mood'),
                    data_get($metadata, 'story_music.genre'),
                ])),
                'meta' => [
                    'source_media_id' => $media->id,
                    'source_media_type' => $media->mediaable_type,
                    'source_disk' => $media->disk,
                    'creator_user_id' => $owner->id,
                    'consent_recorded_at' => data_get($metadata, 'story_music.consent_recorded_at'),
                ],
                'review_status' => $isActive ? 'approved' : 'pending',
                'published_at' => $isActive ? now() : null,
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

    private function markMediaExtraction(Media $media, string $status, array $extra = []): void
    {
        $metadata = $media->metadata ?? [];
        $metadata['story_music'] = array_merge((array) data_get($metadata, 'story_music', []), [
            'original_audio_status' => $status,
            'original_audio_updated_at' => now()->toIso8601String(),
        ], $extra);

        $media->metadata = $metadata;
        $media->save();
    }
}
