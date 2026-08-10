<?php

namespace App\Jobs\User\Story;

use Exception;
use App\Models\StoryFrame;
use App\Constants\Filesystem;
use FFMpeg\Format\Video\X264;
use FFMpeg\Coordinate\TimeCode;
use App\Enums\Media\MediaStatus;
use App\Enums\Story\StoryStatus;
use FFMpeg\Coordinate\Dimension;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use FFMpeg\Filters\Video\ResizeFilter;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use App\Services\Filesystem\Delete\FileDeleteService;
use App\Services\Filesystem\Upload\VideoUploadService;

class ProcessStoryVideo implements ShouldQueue
{
    use Queueable;

    private $frameData;

    public $deleteWhenMissingModels = true;
    public $timeout = (60 * 30); // 30 minutes

    public function __construct(StoryFrame $frameData)
    {
        $this->frameData = $frameData;
    }

    public function handle(): void
    {
        $frameMedia = null;
        $videoTempOldPath = null;

        try {
            $videoUploadService = app(VideoUploadService::class);
            $fileDeleteService = app(FileDeleteService::class);
            $this->frameData = StoryFrame::with('media')->find($this->frameData->id);

            if(empty($this->frameData)) {
                return;
            }

            $frameMedia = $this->frameData->media->first();

            if(empty($frameMedia)) {
                throw new Exception('Story video media was not found.');
            }

            $this->updateProcessingProgress($frameMedia, 5, 'processing');

            $videoTempOldPath = $this->prepareLocalSourceVideo($frameMedia, $videoUploadService);
            $oldDisk = $frameMedia->disk;
            $oldPath = $frameMedia->source_path;
            $oldSize = (int) $frameMedia->size;

            $videoTempNewPath = $videoUploadService->generateVideoTemporaryFilePath("processed.{$videoUploadService->videoDefaultExtension}");

            $videoOldAbsLocalPath = storage_local_path($videoTempOldPath);
            $videoNewAbsLocalPath = storage_local_path($videoTempNewPath);
            $clipStartSeconds = $this->clipStartSeconds();
            $clipDurationSeconds = $this->clipDurationSeconds();

            $ffmpeg = $videoUploadService->getFFMpeg();
            $format = (new X264())
                ->setKiloBitrate(0)
                ->setAudioKiloBitrate((int) config('story.processing.video.audio_bitrate'))
                ->setAdditionalParameters([
                    '-preset',
                    config('story.processing.video.preset'),
                    '-crf',
                    (string) config('story.processing.video.crf'),
                    '-pix_fmt',
                    'yuv420p',
                    '-movflags',
                    '+faststart',
                ]);

            $lastProgress = 5;

            $format->on('progress', function($video, $format, $percentage) use ($frameMedia, &$lastProgress) {
                $progress = max(10, min(90, (int) floor(10 + ($percentage * 0.8))));

                if($progress >= ($lastProgress + 5) || $progress >= 90) {
                    $lastProgress = $progress;
                    $this->updateProcessingProgress($frameMedia, $progress, 'processing');
                }
            });

            $video = $ffmpeg->open($videoOldAbsLocalPath);

            $video->filters()->clip(TimeCode::fromSeconds($clipStartSeconds), TimeCode::fromSeconds($clipDurationSeconds));

            $video->filters()->resize(new Dimension(1080, 1920), ResizeFilter::RESIZEMODE_INSET)->synchronize();

            $video->filters()->pad(new Dimension(1080, 1920), function ($width, $height) {
                return [0, ($height - 1920) / 2];
            })->synchronize();

            $video->save($format, $videoNewAbsLocalPath);
            $this->updateProcessingProgress($frameMedia, 92, 'uploading');

            if(! $this->storyFrameStillExists($frameMedia)) {
                $fileDeleteService->setStorageDisk('local')->deleteFile($videoTempNewPath);

                return;
            }

            if(file_exists($videoNewAbsLocalPath)) {
                $targetDisk = $this->targetStorageDisk($frameMedia);
                $videoData = $videoUploadService
                    ->setStorageDisk($targetDisk)
                    ->setNamespace(Filesystem::mediaNamespace('stories/videos'))
                    ->upload($videoNewAbsLocalPath);

                if(! $this->storyFrameStillExists($frameMedia)) {
                    $fileDeleteService->setStorageDisk($targetDisk)->deleteFile($videoData['video_path']);
                    $fileDeleteService->setStorageDisk('local')->deleteFile($videoTempNewPath);

                    return;
                }

                $metadata = $frameMedia->metadata ?? [];
                $metadata = array_merge($metadata, [
                    'provider' => data_get($metadata, 'provider') === 'r2_temp' ? 'r2' : data_get($metadata, 'provider'),
                    'processing_progress' => 100,
                    'processing_state' => 'processed',
                    'processing_updated_at' => now()->toIso8601String(),
                    'processed_at' => now()->toIso8601String(),
                    'original_size' => $oldSize,
                    'optimized_size' => (int) ($videoData['video_size'] ?? $oldSize),
                    'optimization_ratio' => $this->optimizationRatio($oldSize, (int) ($videoData['video_size'] ?? $oldSize)),
                ]);

                $frameMedia->source_path = $videoData['video_path'];
                $frameMedia->disk = $videoData['disk'];
                $frameMedia->status = MediaStatus::PROCESSED;
                $frameMedia->extension = $videoUploadService->videoDefaultExtension;
                $frameMedia->mime = 'video/mp4';
                $frameMedia->size = $videoData['video_size'] ?? $oldSize;
                $frameMedia->metadata = $metadata;
                $frameMedia->save();

                $this->frameData->duration_seconds = $clipDurationSeconds;
                $this->frameData->status = StoryStatus::ACTIVE;

                $this->frameData->save();

                $this->deleteOriginalSource($oldDisk, $oldPath, $videoTempOldPath, $fileDeleteService);
                $fileDeleteService->setStorageDisk('local')->deleteFile($videoTempNewPath);
            }
        }

        catch (Exception $e) {
            $failedProgress = max(1, min(99, (int) data_get($frameMedia?->metadata, 'processing_progress', 1)));

            $this->updateProcessingProgress($frameMedia, $failedProgress, 'failed');

            Log::error('Story video processing failed after 5 attempts. Error: ' . $e->getMessage());

            $this->fail();
        }
    }

    public function tries(): int
    {
        return 5;
    }

    private function clipStartSeconds(): int
    {
        return max(0, (int) data_get($this->frameData->meta, 'video.clip_start_seconds', 0));
    }

    private function clipDurationSeconds(): int
    {
        $configuredClipSize = max(1, (int) config('story.video_clip_size'));
        $storedDuration = (int) data_get($this->frameData->meta, 'video.duration_seconds', $this->frameData->duration_seconds);

        return max(1, min($configuredClipSize, $storedDuration ?: $configuredClipSize));
    }

    private function prepareLocalSourceVideo($frameMedia, VideoUploadService $videoUploadService): string
    {
        if(! in_array(data_get($frameMedia->metadata, 'provider'), ['r2_temp', 'r2_direct'], true)) {
            return $frameMedia->source_path;
        }

        if(data_get($frameMedia->metadata, 'upload_state') !== 'uploaded') {
            throw new Exception('Story video upload has not completed yet.');
        }

        $localPath = $videoUploadService->generateVideoTemporaryFilePath($frameMedia->extension ?: 'mp4');

        Storage::disk('local')->makeDirectory(dirname($localPath));

        $readStream = Storage::disk($frameMedia->disk)->readStream($frameMedia->source_path);

        if(! is_resource($readStream)) {
            throw new Exception('Unable to read the R2 temporary story video stream.');
        }

        $localAbsolutePath = storage_local_path($localPath);
        $writeStream = fopen($localAbsolutePath, 'w+b');

        if(! is_resource($writeStream)) {
            fclose($readStream);

            throw new Exception('Unable to create a local temporary story video file.');
        }

        stream_copy_to_stream($readStream, $writeStream);

        fclose($readStream);
        fclose($writeStream);

        return $localPath;
    }

    private function targetStorageDisk($frameMedia): string
    {
        return (string) data_get($frameMedia->metadata, 'final_disk', $frameMedia->thumbnail_disk ?: $frameMedia->disk);
    }

    private function updateProcessingProgress($frameMedia, int $progress, string $state): void
    {
        if(empty($frameMedia) || ! $this->storyFrameStillExists($frameMedia)) {
            return;
        }

        $metadata = $frameMedia->metadata ?? [];
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
            $metadata['processing_error'] = 'Story video processing failed.';
            $frameMedia->status = MediaStatus::FAILED;
        }
        elseif(! $frameMedia->status->isProcessed()) {
            $frameMedia->status = MediaStatus::PROCESSING;
        }

        $frameMedia->metadata = $metadata;
        $frameMedia->save();
    }

    private function storyFrameStillExists($frameMedia = null): bool
    {
        if(empty($this->frameData) || ! StoryFrame::whereKey($this->frameData->id)->exists()) {
            return false;
        }

        if(! empty($frameMedia) && ! $frameMedia->newQuery()->whereKey($frameMedia->id)->exists()) {
            return false;
        }

        return true;
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
}
