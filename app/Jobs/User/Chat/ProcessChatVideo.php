<?php

namespace App\Jobs\User\Chat;

use App\Constants\Filesystem;
use App\Enums\Media\MediaStatus;
use App\Events\User\Chat\MessageMediaReadyEvent;
use App\Models\Message;
use App\Services\Filesystem\Delete\FileDeleteService;
use App\Services\Filesystem\Upload\ImageUploadService;
use App\Services\Filesystem\Upload\VideoThumbnailService;
use App\Services\Filesystem\Upload\VideoUploadService;
use Exception;
use FFMpeg\Format\Video\X264;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class ProcessChatVideo implements ShouldQueue
{
    use Queueable;

    public $deleteWhenMissingModels = true;
    public $timeout = 60 * 30;

    private Message $messageData;

    public function __construct(Message $messageData)
    {
        $this->messageData = $messageData;
        $this->onConnection(config('media.queue_connection'));
    }

    public function handle(): void
    {
        $messageMedia = null;
        $videoTempOldPath = null;
        $videoOutputPath = null;
        $remoteSource = false;

        try {
            $videoUploadService = app(VideoUploadService::class);
            $fileDeleteService = app(FileDeleteService::class);
            $this->messageData = Message::query()->with('media')->find($this->messageData->id);

            if(empty($this->messageData)) {
                return;
            }

            $messageMedia = $this->messageData->media;

            if(empty($messageMedia) || ! $messageMedia->type->isVideo() || $messageMedia->status->isProcessed()) {
                return;
            }

            $this->updateProcessingProgress($messageMedia, 10, 'preparing');

            $remoteSource = in_array(data_get($messageMedia->metadata, 'provider'), ['r2_temp', 'r2_direct'], true);
            $videoTempOldPath = $this->prepareLocalSourceVideo($messageMedia, $videoUploadService);
            $videoUploadService->validateVideoSource($videoTempOldPath);
            $oldDisk = $messageMedia->disk;
            $oldPath = $messageMedia->source_path;
            $oldSize = (int) $messageMedia->size;
            $targetDisk = $this->targetStorageDisk($messageMedia);

            $this->ensureThumbnail($messageMedia, $videoTempOldPath, $targetDisk);

            if(config('chat.enable_video_compression') || in_array(data_get($messageMedia->metadata, 'provider'), ['r2_temp', 'r2_direct'], true)) {
                $this->updateProcessingProgress($messageMedia, 20, 'transcoding');
                $videoOutputPath = $this->compressVideo($videoUploadService, storage_local_path($videoTempOldPath), $messageMedia);
            }
            else {
                $videoUploadService->setDefaultExtension($messageMedia->extension ?: 'mp4');
            }

            $this->updateProcessingProgress($messageMedia, 92, 'publishing');

            $videoData = $videoUploadService
                ->setStorageDisk($targetDisk)
                ->setNamespace(Filesystem::mediaNamespace('chats/videos'))
                ->upload(storage_local_path($videoOutputPath ?: $videoTempOldPath));

            $metadata = $messageMedia->metadata ?? [];
            $processedSize = (int) ($videoData['video_size'] ?? $oldSize);
            $squareSize = max(1, (int) config('chat.processing.video.square_size', 720));

            $messageMedia->source_path = $videoData['video_path'];
            $messageMedia->disk = $videoData['disk'];
            $messageMedia->status = MediaStatus::PROCESSED;
            $messageMedia->extension = $videoUploadService->videoDefaultExtension;
            $messageMedia->mime = 'video/mp4';
            $messageMedia->size = $processedSize;
            $messageMedia->metadata = array_merge($metadata, [
                'provider' => in_array(data_get($metadata, 'provider'), ['r2_temp', 'r2_direct'], true) ? 'r2' : data_get($metadata, 'provider'),
                'dimensions' => [
                    'width' => $squareSize,
                    'height' => $squareSize,
                ],
                'aspect_ratio' => 1,
                'is_portrait' => false,
                'processed_at' => now()->toIso8601String(),
                'processing_progress' => 100,
                'processing_state' => 'processed',
                'processing_updated_at' => now()->toIso8601String(),
                'original_size' => $oldSize,
                'optimized_size' => $processedSize,
                'optimization_ratio' => $this->optimizationRatio($oldSize, $processedSize),
            ]);
            $messageMedia->save();

            $this->deleteOriginalSource($oldDisk, $oldPath, $videoTempOldPath, $fileDeleteService, $messageMedia->id);
            try {
                event(new MessageMediaReadyEvent($this->messageData->refresh()->load([
                    'reactions',
                    'media',
                    'participant',
                    'user:id,first_name,last_name,username,avatar,verified',
                    'parent.user:id,first_name,last_name,username,avatar,verified',
                    'parent.participant',
                    'parent.media',
                    'parent.linkSnapshot',
                    'linkSnapshot'
                ])));
            }
            catch (\Throwable $e) {
                Log::warning('Chat video ready notification failed.', ['message_id' => $this->messageData->id, 'error' => $e->getMessage()]);
            }
        }
        catch (\Throwable $e) {
            Log::error('Chat video processing failed. Error: ' . $e->getMessage(), [
                'message_id' => $this->messageData->id ?? null,
                'media_id' => $messageMedia?->id,
            ]);

            if($messageMedia) {
                $this->updateProcessingProgress(
                    $messageMedia,
                    (int) data_get($messageMedia->metadata, 'processing_progress', 0),
                    'failed'
                );
            }

            throw $e;
        }
        finally {
            if($remoteSource && $videoTempOldPath) Storage::disk('local')->delete($videoTempOldPath);
            if($videoOutputPath) Storage::disk('local')->delete($videoOutputPath);
        }
    }

    public function tries(): int
    {
        return 5;
    }

    public function middleware(): array
    {
        return [(new \Illuminate\Queue\Middleware\WithoutOverlapping('messageData:' . $this->messageData->id))
            ->releaseAfter(60)->expireAfter($this->timeout + 60)];
    }

    public function backoff(): array
    {
        return [30, 120, 300, 600];
    }

    private function prepareLocalSourceVideo($messageMedia, VideoUploadService $videoUploadService): string
    {
        if(! in_array(data_get($messageMedia->metadata, 'provider'), ['r2_temp', 'r2_direct'], true)) {
            return $messageMedia->source_path;
        }

        if(data_get($messageMedia->metadata, 'upload_state') !== 'uploaded') {
            throw new Exception('Chat video upload has not completed yet.');
        }

        $localPath = $videoUploadService->generateVideoTemporaryFilePath($messageMedia->extension ?: 'mp4');

        Storage::disk('local')->makeDirectory(dirname($localPath));

        $readStream = Storage::disk($messageMedia->disk)->readStream($messageMedia->source_path);

        if(! is_resource($readStream)) {
            throw new Exception('Unable to read the R2 temporary chat video stream.');
        }

        $localAbsolutePath = storage_local_path($localPath);
        $writeStream = fopen($localAbsolutePath, 'w+b');

        if(! is_resource($writeStream)) {
            fclose($readStream);

            throw new Exception('Unable to create a local temporary chat video file.');
        }

        stream_copy_to_stream($readStream, $writeStream);

        fclose($readStream);
        fclose($writeStream);

        return $localPath;
    }

    private function compressVideo(VideoUploadService $videoUploadService, string $videoPath, $messageMedia): string
    {
        $ffmpeg = $videoUploadService->getFFMpeg();
        $video = $ffmpeg->open($videoPath);
        $squareSize = max(1, (int) config('chat.processing.video.square_size', 720));

        $format = new X264();
        $format->setKiloBitrate(0)
            ->setAudioKiloBitrate((int) config('chat.processing.video.audio_bitrate'))
            ->setAdditionalParameters([
                '-preset',
                config('chat.processing.video.preset'),
                '-crf',
                (string) config('chat.processing.video.crf'),
                '-movflags',
                '+faststart',
                '-pix_fmt',
                'yuv420p',
                '-vf',
                "scale={$squareSize}:{$squareSize}:force_original_aspect_ratio=increase,crop={$squareSize}:{$squareSize}",
            ]);

        $lastSavedProgress = 20;

        $format->on('progress', function($video, $format, $percentage) use ($messageMedia, &$lastSavedProgress) {
            $progress = min(85, max(20, (int) round(20 + (((int) $percentage) * 0.65))));

            if($progress >= ($lastSavedProgress + 5) || $progress >= 85) {
                $lastSavedProgress = $progress;
                $this->updateProcessingProgress($messageMedia, $progress, 'transcoding');
            }
        });

        $videoTempNewPath = $videoUploadService->generateVideoTemporaryFilePath("compressed.{$videoUploadService->videoDefaultExtension}");
        try {
            $video->save($format, storage_local_path($videoTempNewPath));
            return $videoTempNewPath;
        }
        catch (\Throwable $e) {
            Storage::disk('local')->delete($videoTempNewPath);
            throw $e;
        }
    }

    private function targetStorageDisk($messageMedia): string
    {
        return (string) data_get($messageMedia->metadata, 'final_disk', $messageMedia->thumbnail_disk ?: $messageMedia->disk);
    }

    private function ensureThumbnail($messageMedia, string $videoLocalPath, string $targetDisk): void
    {
        if(filled($messageMedia->thumbnail_path)) {
            return;
        }

        $thumbnailPath = null;

        try {
            $thumbnailPath = app(VideoThumbnailService::class)
                ->setSecondsOffset(1)
                ->generateThumbnail($videoLocalPath);

            $imageData = app(ImageUploadService::class)
                ->load($thumbnailPath)
                ->setNamespace(Filesystem::mediaNamespace('chats/video_thumbnails'))
                ->setStorageDisk($targetDisk)
                ->compress(config('chat.processing.video_thumbnail.compress_rate'))
                ->upload();

            $messageMedia->thumbnail_path = $imageData['image_path'];
            $messageMedia->thumbnail_size = $imageData['image_size'];
            $messageMedia->thumbnail_disk = $imageData['disk'];
            $messageMedia->save();
        }
        catch (\Throwable $e) {
            Log::warning('Chat video thumbnail generation failed. Error: ' . $e->getMessage(), [
                'media_id' => $messageMedia->id ?? null,
            ]);
            throw $e;
        }
        finally {
            if($thumbnailPath && is_file($thumbnailPath)) {
                unlink($thumbnailPath);
            }
        }
    }

    private function updateProcessingProgress($messageMedia, int $progress, string $state): void
    {
        if(empty($messageMedia) || ! $this->messageMediaStillExists($messageMedia)) {
            return;
        }

        $metadata = $messageMedia->metadata ?? [];
        $progress = max(0, min(100, $progress));

        if($state !== 'failed') {
            $progress = max((int) data_get($metadata, 'processing_progress', 0), $progress);
        }

        $metadata['processing_progress'] = $progress;
        $metadata['processing_state'] = $state;
        $metadata['processing_updated_at'] = now()->toIso8601String();

        if(blank(data_get($metadata, 'processing_started_at'))) {
            $metadata['processing_started_at'] = now()->toIso8601String();
        }

        if($state === 'failed') {
            $metadata['processing_error'] = 'Chat video processing failed.';
            $messageMedia->status = MediaStatus::FAILED;
        }
        elseif(! $messageMedia->status->isProcessed()) {
            $messageMedia->status = MediaStatus::PROCESSING;
        }

        $messageMedia->metadata = $metadata;
        $messageMedia->save();
    }

    private function messageMediaStillExists($messageMedia = null): bool
    {
        if(empty($this->messageData) || ! Message::query()->whereKey($this->messageData->id)->exists()) {
            return false;
        }

        if(! empty($messageMedia) && ! $messageMedia->newQuery()->whereKey($messageMedia->id)->exists()) {
            return false;
        }

        return true;
    }

    private function deleteOriginalSource(string $oldDisk, string $oldPath, string $localPath, FileDeleteService $fileDeleteService, int $mediaId): void
    {
        $fileDeleteService->setStorageDisk('local')->deleteFile($localPath);

        if($oldDisk !== 'local') {
            try {
                if(! Storage::disk($oldDisk)->delete($oldPath)) {
                    throw new \RuntimeException('Original media deletion failed.');
                }
            }
            catch (\Throwable $e) {
                Log::warning('Processed video original cleanup deferred.', ['media_id' => $mediaId]);
                try {
                    \App\Jobs\CleanupProcessedMediaSource::dispatch($mediaId, $oldDisk, $oldPath);
                }
                catch (\Throwable $queueError) {
                    Log::error('Original cleanup could not be queued; temp lifecycle must remove it.', ['media_id' => $mediaId]);
                }
            }
        }
    }

    private function optimizationRatio(int $oldSize, int $newSize): int
    {
        if($oldSize <= 0 || $newSize <= 0) {
            return 0;
        }

        return max(0, min(100, (int) round((1 - ($newSize / $oldSize)) * 100)));
    }
}
