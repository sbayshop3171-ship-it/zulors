<?php

namespace App\Jobs\User\Timeline;

use Exception;
use App\Models\Post;
use App\Constants\Filesystem;
use FFMpeg\Coordinate\Dimension;
use App\Services\Media\Publication\MediaEncodingProfile;
use App\Enums\Post\PostStatus;
use FFMpeg\Filters\Video\ResizeFilter;
use App\Enums\Media\MediaStatus;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use App\Events\User\Timeline\MediaProcessedEvent;
use App\Events\User\Timeline\MediaUpdatedEvent;
use App\Events\User\Timeline\PublicTimelinePostCreatedEvent;
use App\Services\Filesystem\Delete\FileDeleteService;
use App\Services\Filesystem\Upload\ImageUploadService;
use App\Services\Filesystem\Upload\VideoUploadService;
use App\Services\Filesystem\Upload\VideoThumbnailService;

class ConvertAndCompressPostVideo implements ShouldQueue
{
    use Queueable;

    public $timeout = (60 * 60 * 6); // 6 hours

    private $postData;

    public function __construct(Post $postData)
    {
        $this->postData = $postData;
        $this->onConnection(config('media.queue_connection'));
        $this->timeout = max(60 * 30, (int) config('post.processing.video.timeout', $this->timeout));
    }

    public function handle(): void
    {
        $postMedia = null;
        $videoUploadService = null;
        $fileDeleteService = null;
        $videoTempOldPath = null;
        $videoTempNewPath = null;
        $remoteSource = false;

        try {
            $postMedia = $this->postData->media()->first();

            if(empty($postMedia)) {
                return;
            }

            $postWasAlreadyActive = $this->postData->status === PostStatus::ACTIVE;
            $instantR2Optimization = $this->isInstantR2MediaAwaitingOptimization($postMedia);

            if($postMedia->status->isProcessed() && $postMedia->disk !== 'cloudflare_stream' && ! $instantR2Optimization) {
                return;
            }

            if($postMedia->disk === 'cloudflare_stream') {
                if($postMedia->status->isProcessed()) {
                    $this->postData->status = PostStatus::ACTIVE;
                    $this->postData->save();

                    event(new MediaProcessedEvent($postMedia->refresh(), $this->postData->user_id));

                    if(! $postWasAlreadyActive) {
                        event(new PublicTimelinePostCreatedEvent($this->postData->refresh()));
                    }
                }

                return;
            }

            $videoUploadService = app(VideoUploadService::class);
            $fileDeleteService = app(FileDeleteService::class);

            if (! $videoUploadService) {
                throw new Exception('Required services are not available. Ensure that fileUploaderService and ffmpegService are properly injected.');
            }

            $this->updateProcessingProgress($postMedia, 10, 'preparing');

            // Get video video local temporary path
            $remoteSource = in_array(data_get($postMedia->metadata, 'provider'), ['r2_temp', 'r2_direct'], true);
            $videoTempOldPath = $this->prepareLocalSourceVideo($postMedia, $videoUploadService);
            $videoUploadService->validateVideoSource($videoTempOldPath);

            $this->updateProcessingProgress($postMedia, 15, 'transcoding');

            // Generate new video temporary path for compressed video marking it as compressed. [compressed.mp4]
            $videoTempNewPath = $videoUploadService->generateVideoTemporaryFilePath("compressed.{$videoUploadService->videoDefaultExtension}");

            $ffmpeg = $videoUploadService->getFFMpeg();
            $videoOldAbsLocalPath = storage_local_path($videoTempOldPath);
            $videoNewAbsLocalPath = storage_local_path($videoTempNewPath);

            if(config('logging.debugging.video_process_logging')) {
                $fileOldExists = (file_exists($videoOldAbsLocalPath)) ? 'Yes' : 'No';

                Log::info("Video with path: {$videoOldAbsLocalPath} loaded. Video file exists: {$fileOldExists}");
            }

            // Compress video and save to new path converting it to mp4
            $video = $ffmpeg->open($videoOldAbsLocalPath);

            $this->resizeVideoIfNeeded($video, $videoUploadService, $videoOldAbsLocalPath);

            $format = MediaEncodingProfile::x264(
                (string) config('post.processing.video.crf'),
                (string) config('post.processing.video.preset'),
                (int) config('post.processing.video.audio_bitrate')
            );

            $lastSavedTranscodeProgress = 15;

            $format->on('progress', function ($video, $format, $percentage) use ($postMedia, &$lastSavedTranscodeProgress) {
                $transcodeProgress = min(85, max(15, (int) round(15 + (((int) $percentage) * 0.70))));

                if($transcodeProgress >= ($lastSavedTranscodeProgress + 5) || $transcodeProgress >= 85) {
                    $lastSavedTranscodeProgress = $transcodeProgress;

                    $this->updateProcessingProgress($postMedia, $transcodeProgress, 'transcoding');
                }
            });

            if(config('brand.videos_watermark_enabled')) {
                $watermarkConfig = config('assets.watermark');
                $video->filters()->watermark(public_path($watermarkConfig['local_path']), [
                    'position' => $watermarkConfig['video']['position'],
                    'x' => $watermarkConfig['video']['x'],
                    'y' => $watermarkConfig['video']['y'],
                ]);
            }

            $video->save($format, $videoNewAbsLocalPath);

            if(file_exists($videoNewAbsLocalPath)) {
                $targetDisk = $this->targetStorageDisk($postMedia);
                $videoPresentationMetadata = $this->videoPresentationMetadata($videoUploadService, $videoNewAbsLocalPath);

                $this->updateProcessingProgress($postMedia, 88, 'thumbnailing');

                $this->ensureThumbnail($postMedia, $videoTempNewPath, $targetDisk);

                $this->updateProcessingProgress($postMedia, 92, 'publishing');

                // Upload compressed video to public disk and update post media
                // Public disk is determined by post media with round robin algorithm
                // and it is not local public folder of the application.

                $videoData = $videoUploadService
                    ->setStorageDisk($targetDisk)
                    ->setNamespace(Filesystem::mediaNamespace('posts/videos'))
                    ->upload($videoNewAbsLocalPath);

                $oldDisk = $postMedia->disk;
                $oldPath = $postMedia->source_path;
                $oldSize = (int) $postMedia->size;
                $metadata = $postMedia->metadata ?? [];

                $postMedia->source_path = $videoData['video_path'];
                $postMedia->disk = $videoData['disk'];
                $postMedia->status = MediaStatus::PROCESSED;
                $postMedia->extension = $videoUploadService->videoDefaultExtension;
                $postMedia->mime = 'video/mp4';
                $postMedia->size = $videoData['video_size'] ?? filesize($videoNewAbsLocalPath);
                $postMedia->metadata = array_merge($metadata, $videoPresentationMetadata, [
                    'provider' => in_array(data_get($metadata, 'provider'), ['r2_temp', 'r2_direct'], true) ? 'r2' : data_get($metadata, 'provider'),
                    'processed_at' => now()->toIso8601String(),
                    'processing_progress' => 100,
                    'processing_state' => 'processed',
                    'processing_updated_at' => now()->toIso8601String(),
                    'processing_error' => null,
                    'background_processing_state' => 'processed',
                    'background_processing_progress' => 100,
                    'original_size' => $oldSize,
                    'optimized_size' => (int) $postMedia->size,
                    'optimization_ratio' => $this->optimizationRatio($oldSize, (int) $postMedia->size),
                ]);
                $postMedia->save();

                $this->postData->status = PostStatus::ACTIVE;

                $this->postData->save();

                if(config('logging.debugging.video_process_logging')) {
                    $fileNewExists = file_exists($videoNewAbsLocalPath) ? 'Yes' : 'No';

                    Log::info("Compressed video with new path: {$videoNewAbsLocalPath} saved. Video new file exists: {$fileNewExists}");
                }

                $this->deleteOriginalSource($oldDisk, $oldPath, $videoTempOldPath, $fileDeleteService, $postMedia->id);
                $fileDeleteService->setStorageDisk('local')->deleteFile($videoTempNewPath);

                // Broadcast video processed event with updated post media and user id
                // to notify users that video has been processed.

                try {
                    event(new MediaProcessedEvent($postMedia->refresh(), $this->postData->user_id));

                    if(! $postWasAlreadyActive) {
                        event(new PublicTimelinePostCreatedEvent($this->postData->refresh()));
                    }
                } catch (Exception $e) {
                    Log::error('Failed to broadcast video processed event: ' . $e->getMessage());
                }
            }
        }

        catch (\Throwable $e) {
            Log::error('Post video processing failed after 5 attempts. Error: ' . $e->getMessage());

            if($postMedia) {
                $this->updateProcessingProgress($postMedia, (int) data_get($postMedia->metadata, 'processing_progress', 0), 'failed');
            }

            throw $e;
        }
        finally {
            if($remoteSource && $videoTempOldPath) Storage::disk('local')->delete($videoTempOldPath);
            if($videoTempNewPath) Storage::disk('local')->delete($videoTempNewPath);
        }
    }

    public function tries(): int
    {
        return 5;
    }

    public function middleware(): array
    {
        return [(new \Illuminate\Queue\Middleware\WithoutOverlapping('postData:' . $this->postData->id))
            ->releaseAfter(60)->expireAfter($this->timeout + 60)];
    }

    public function backoff(): array
    {
        return [30, 120, 300, 600];
    }

    private function prepareLocalSourceVideo($postMedia, VideoUploadService $videoUploadService): string
    {
        if(! in_array(data_get($postMedia->metadata, 'provider'), ['r2_temp', 'r2_direct'], true)) {
            return $postMedia->source_path;
        }

        if(data_get($postMedia->metadata, 'upload_state') !== 'uploaded') {
            throw new Exception('R2 direct video upload has not been completed yet.');
        }

        $localPath = $videoUploadService->generateVideoTemporaryFilePath($postMedia->extension ?: 'mp4');

        Storage::disk('local')->makeDirectory(dirname($localPath));

        $readStream = Storage::disk($postMedia->disk)->readStream($postMedia->source_path);

        if(! is_resource($readStream)) {
            throw new Exception('Unable to read the R2 temporary video stream.');
        }

        $localAbsolutePath = storage_local_path($localPath);
        $writeStream = fopen($localAbsolutePath, 'w+b');

        if(! is_resource($writeStream)) {
            fclose($readStream);

            throw new Exception('Unable to create local temporary video file.');
        }

        stream_copy_to_stream($readStream, $writeStream);

        fclose($readStream);
        fclose($writeStream);

        return $localPath;
    }

    private function targetStorageDisk($postMedia): string
    {
        if(in_array(data_get($postMedia->metadata, 'provider'), ['r2_temp', 'r2_direct'], true)) {
            return (string) data_get($postMedia->metadata, 'final_disk', config('media.cloudflare.r2.final_disk'));
        }

        return $postMedia->disk;
    }

    private function resizeVideoIfNeeded($video, VideoUploadService $videoUploadService, string $videoLocalAbsolutePath): void
    {
        $maxWidth = (int) config('post.processing.video.max_width', 1080);
        $maxHeight = (int) config('post.processing.video.max_height', 1920);

        if($maxWidth < 1 || $maxHeight < 1) {
            return;
        }

        $stream = $videoUploadService->getFFProbe()->streams($videoLocalAbsolutePath)->videos()->first();

        if(empty($stream)) {
            return;
        }

        $width = (int) $stream->get('width');
        $height = (int) $stream->get('height');

        if($width <= 0 || $height <= 0 || ($width <= $maxWidth && $height <= $maxHeight)) {
            return;
        }

        $scale = min($maxWidth / $width, $maxHeight / $height);
        $targetWidth = $this->makeEven((int) floor($width * $scale));
        $targetHeight = $this->makeEven((int) floor($height * $scale));

        $video->filters()->resize(new Dimension($targetWidth, $targetHeight), ResizeFilter::RESIZEMODE_INSET)->synchronize();
    }

    private function updateProcessingProgress($postMedia, int $progress, string $state): void
    {
        if(empty($postMedia)) {
            return;
        }

        $metadata = $postMedia->metadata ?? [];
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
            if($this->isInstantR2MediaAwaitingOptimization($postMedia)) {
                $metadata['processing_error'] = 'Background video optimization failed. Raw playback remains available.';
            }
            else {
                $postMedia->status = MediaStatus::FAILED;
            }
        }
        elseif(! $postMedia->status->isProcessed()) {
            $postMedia->status = MediaStatus::PROCESSING;
        }

        if($state !== 'failed') {
            $metadata['background_processing_state'] = $state;
            $metadata['background_processing_progress'] = $progress;
        }

        if($state === 'failed') {
            $metadata['background_processing_state'] = 'failed';
        }

        $postMedia->metadata = $metadata;
        $postMedia->save();

        $this->broadcastMediaUpdated($postMedia);
    }

    private function isInstantR2MediaAwaitingOptimization($postMedia): bool
    {
        if(empty($postMedia)) {
            return false;
        }

        $metadata = $postMedia->metadata ?? [];

        return $postMedia->status->isProcessed()
            && in_array(data_get($metadata, 'provider'), ['r2_temp', 'r2_direct'], true)
            && data_get($metadata, 'upload_state') === 'uploaded'
            && blank(data_get($metadata, 'processed_at'));
    }

    private function broadcastMediaUpdated($postMedia): void
    {
        $postMedia->loadMissing('mediaable');

        if(! ($postMedia->mediaable instanceof Post)) {
            return;
        }

        $userId = $postMedia->mediaable->user_id;

        try {
            event(new MediaUpdatedEvent($postMedia->refresh(), $userId));
        }
        catch (\Throwable $e) {
            report($e);
        }
    }

    private function ensureThumbnail($postMedia, string $videoLocalPath, string $targetDisk): void
    {
        if(! empty($postMedia->thumbnail_path)) {
            return;
        }

        $videoThumbnailService = app(VideoThumbnailService::class);
        $imageUploadService = app(ImageUploadService::class);
        $thumbnailLocalPath = $videoThumbnailService->generateThumbnail($videoLocalPath);

        $imageData = $imageUploadService
            ->load($thumbnailLocalPath)
            ->setNamespace(Filesystem::mediaNamespace('posts/video_thumbnails'))
            ->setStorageDisk($targetDisk)
            ->watermark()
            ->compress(config('post.processing.thumbnail.compress_rate'))
            ->upload();

        $metadata = $postMedia->metadata ?? [];
        $metadata = array_merge($metadata, $this->imagePresentationMetadata($thumbnailLocalPath));

        $postMedia->thumbnail_path = $imageData['image_path'];
        $postMedia->thumbnail_size = $imageData['image_size'];
        $postMedia->thumbnail_disk = $imageData['disk'];
        $postMedia->metadata = $metadata;
        $postMedia->save();

        if(is_file($thumbnailLocalPath)) {
            unlink($thumbnailLocalPath);
        }
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

    private function videoPresentationMetadata(VideoUploadService $videoUploadService, string $videoLocalPath): array
    {
        try {
            return $this->presentationMetadataFromDimensions(
                $videoUploadService->getVideoDimensions($videoLocalPath)
            );
        }
        catch (\Throwable $e) {
            Log::warning('Video dimensions metadata skipped. Error: ' . $e->getMessage());

            return [];
        }
    }

    private function imagePresentationMetadata(string $imagePath): array
    {
        $dimensions = getimagesize($imagePath);

        if(empty($dimensions)) {
            return [];
        }

        return $this->presentationMetadataFromDimensions([
            'width' => (int) $dimensions[0],
            'height' => (int) $dimensions[1],
        ]);
    }

    private function presentationMetadataFromDimensions(array $dimensions): array
    {
        $width = (int) ($dimensions['width'] ?? 0);
        $height = (int) ($dimensions['height'] ?? 0);

        if($width < 1 || $height < 1) {
            return [];
        }

        return [
            'dimensions' => [
                'width' => $width,
                'height' => $height,
            ],
            'aspect_ratio' => round($width / $height, 6),
            'is_portrait' => $width < $height,
        ];
    }

    private function makeEven(int $value): int
    {
        $value = max(2, $value);

        return $value % 2 === 0 ? $value : $value - 1;
    }
}
