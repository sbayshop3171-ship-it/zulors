<?php

namespace App\Jobs\User\Timeline;

use Exception;
use App\Models\Post;
use App\Constants\Filesystem;
use FFMpeg\Coordinate\Dimension;
use FFMpeg\Format\Video\X264;
use App\Enums\Post\PostStatus;
use FFMpeg\Filters\Video\ResizeFilter;
use App\Enums\Media\MediaStatus;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use App\Events\User\Timeline\MediaProcessedEvent;
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
        $this->timeout = max(60 * 30, (int) config('post.processing.video.timeout', $this->timeout));
    }

    public function handle(): void
    {
        $postMedia = null;
        $videoUploadService = null;
        $fileDeleteService = null;
        $videoTempOldPath = null;

        try {
            $videoUploadService = app(VideoUploadService::class);
            $fileDeleteService = app(FileDeleteService::class);

            $postMedia = $this->postData->media()->first();

            if(empty($postMedia)) {
                return;
            }

            if($postMedia->disk === 'cloudflare_stream') {
                if($postMedia->status->isProcessed()) {
                    $this->postData->status = PostStatus::ACTIVE;
                    $this->postData->save();

                    event(new MediaProcessedEvent($postMedia->refresh(), $this->postData->user_id));
                    event(new PublicTimelinePostCreatedEvent($this->postData->refresh()));
                }

                return;
            }

            if (! $videoUploadService) {
                throw new Exception('Required services are not available. Ensure that fileUploaderService and ffmpegService are properly injected.');
            }

            $this->updateProcessingProgress($postMedia, 10, 'preparing');

            // Get video video local temporary path
            $videoTempOldPath = $this->prepareLocalSourceVideo($postMedia, $videoUploadService);

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

            $format = (new X264())
                ->setKiloBitrate(0)
                ->setAudioKiloBitrate((int) config('post.processing.video.audio_bitrate'))
                ->setAdditionalParameters([
                    '-preset',
                    config('post.processing.video.preset'),
                    '-crf',
                    (string) config('post.processing.video.crf'),
                    '-pix_fmt',
                    'yuv420p',
                    '-movflags',
                    '+faststart',
                ]);

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
                $postMedia->metadata = array_merge($metadata, [
                    'provider' => data_get($metadata, 'provider') === 'r2_temp' ? 'r2' : data_get($metadata, 'provider'),
                    'processed_at' => now()->toIso8601String(),
                    'processing_progress' => 100,
                    'processing_state' => 'processed',
                    'processing_updated_at' => now()->toIso8601String(),
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

                $this->deleteOriginalSource($oldDisk, $oldPath, $videoTempOldPath, $fileDeleteService);
                $fileDeleteService->setStorageDisk('local')->deleteFile($videoTempNewPath);

                // Broadcast video processed event with updated post media and user id
                // to notify users that video has been processed.

                try {
                    event(new MediaProcessedEvent($postMedia->refresh(), $this->postData->user_id));
                    event(new PublicTimelinePostCreatedEvent($this->postData->refresh()));
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

            if(
                $postMedia
                && $videoUploadService
                && $fileDeleteService
                && $videoTempOldPath
                && data_get($postMedia->metadata, 'provider') === 'r2_temp'
                && is_file(storage_local_path($videoTempOldPath))
            ) {
                try {
                    $this->publishOriginalVideoFallback(
                        $postMedia,
                        $videoTempOldPath,
                        $videoUploadService,
                        $fileDeleteService,
                        $e
                    );

                    return;
                }
                catch (\Throwable $fallbackException) {
                    Log::error('Original R2 video fallback also failed. Error: ' . $fallbackException->getMessage());
                }
            }

            throw $e;
        }
    }

    public function tries(): int
    {
        return 5;
    }

    private function prepareLocalSourceVideo($postMedia, VideoUploadService $videoUploadService): string
    {
        if(data_get($postMedia->metadata, 'provider') !== 'r2_temp') {
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
        if(data_get($postMedia->metadata, 'provider') === 'r2_temp') {
            return (string) data_get($postMedia->metadata, 'final_disk', config('media.cloudflare.r2.final_disk'));
        }

        return $postMedia->disk;
    }

    private function publishOriginalVideoFallback(
        $postMedia,
        string $videoTempOldPath,
        VideoUploadService $videoUploadService,
        FileDeleteService $fileDeleteService,
        \Throwable $processingException
    ): void {
        $targetDisk = $this->targetStorageDisk($postMedia);

        try {
            $this->ensureThumbnail($postMedia, $videoTempOldPath, $targetDisk);
        }
        catch (\Throwable $thumbnailException) {
            Log::warning('Video thumbnail fallback skipped. Error: ' . $thumbnailException->getMessage());
        }

        $videoData = $videoUploadService
            ->setStorageDisk($targetDisk)
            ->setNamespace(Filesystem::mediaNamespace('posts/videos'))
            ->upload(storage_local_path($videoTempOldPath));

        $oldDisk = $postMedia->disk;
        $oldPath = $postMedia->source_path;
        $oldSize = (int) $postMedia->size;
        $metadata = $postMedia->metadata ?? [];

        $postMedia->source_path = $videoData['video_path'];
        $postMedia->disk = $videoData['disk'];
        $postMedia->status = MediaStatus::PROCESSED;
        $postMedia->extension = $videoUploadService->videoDefaultExtension;
        $postMedia->mime = 'video/mp4';
        $postMedia->size = $videoData['video_size'] ?? 0;
        $postMedia->metadata = array_merge($metadata, [
            'provider' => 'r2',
            'processed_at' => now()->toIso8601String(),
            'processing_progress' => 100,
            'processing_state' => 'processed',
            'processing_updated_at' => now()->toIso8601String(),
            'processing_fallback' => 'original_upload',
            'processing_error' => str($processingException->getMessage())->limit(500)->toString(),
            'original_size' => $oldSize,
            'optimized_size' => (int) $postMedia->size,
            'optimization_ratio' => $this->optimizationRatio($oldSize, (int) $postMedia->size),
        ]);
        $postMedia->save();

        $this->postData->status = PostStatus::ACTIVE;
        $this->postData->save();

        $this->deleteOriginalSource($oldDisk, $oldPath, $videoTempOldPath, $fileDeleteService);

        event(new MediaProcessedEvent($postMedia->refresh(), $this->postData->user_id));
        event(new PublicTimelinePostCreatedEvent($this->postData->refresh()));

        Log::warning('Published original R2 video because optimized processing failed.', [
            'post_id' => $this->postData->id,
            'media_id' => $postMedia->id,
        ]);
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

        $postMedia->metadata = $metadata;
        $postMedia->save();
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
        $metadata['is_portrait'] = $this->isImagePortrait($thumbnailLocalPath);

        $postMedia->thumbnail_path = $imageData['image_path'];
        $postMedia->thumbnail_size = $imageData['image_size'];
        $postMedia->thumbnail_disk = $imageData['disk'];
        $postMedia->metadata = $metadata;
        $postMedia->save();

        if(is_file($thumbnailLocalPath)) {
            unlink($thumbnailLocalPath);
        }
    }

    private function deleteOriginalSource(string $oldDisk, string $oldPath, string $localPath, FileDeleteService $fileDeleteService): void
    {
        $fileDeleteService->setStorageDisk('local')->deleteFile($localPath);

        if($oldDisk !== 'local') {
            $fileDeleteService->setStorageDisk($oldDisk)->deleteFile($oldPath);
        }
    }

    private function optimizationRatio(int $oldSize, int $newSize): int
    {
        if($oldSize <= 0 || $newSize <= 0) {
            return 0;
        }

        return max(0, min(100, (int) round((1 - ($newSize / $oldSize)) * 100)));
    }

    private function isImagePortrait(string $imagePath): bool
    {
        $dimensions = getimagesize($imagePath);

        if(empty($dimensions)) {
            return false;
        }

        return $dimensions[0] < $dimensions[1];
    }

    private function makeEven(int $value): int
    {
        $value = max(2, $value);

        return $value % 2 === 0 ? $value : $value - 1;
    }
}
