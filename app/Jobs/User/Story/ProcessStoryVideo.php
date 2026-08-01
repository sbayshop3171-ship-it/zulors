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
use FFMpeg\Filters\Video\ResizeFilter;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use App\Services\Filesystem\Delete\FileDeleteService;
use App\Services\Filesystem\Upload\VideoUploadService;

class ProcessStoryVideo implements ShouldQueue
{
    use Queueable;

    private $frameData;

    public $timeout = (60 * 30); // 30 minutes

    public function __construct(StoryFrame $frameData)
    {
        $this->frameData = $frameData;
    }

    public function handle(): void
    {
        $frameMedia = null;

        try {
            $videoUploadService = app(VideoUploadService::class);
            $fileDeleteService = app(FileDeleteService::class);
            $frameMedia = $this->frameData->media->first();

            if(empty($frameMedia)) {
                throw new Exception('Story video media was not found.');
            }

            $this->updateProcessingProgress($frameMedia, 5, 'processing');

            $videoTempOldPath = $frameMedia->source_path;

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

            if(file_exists($videoNewAbsLocalPath)) {
                $videoData = $videoUploadService
                    ->setStorageDisk($frameMedia->disk)
                    ->setNamespace(Filesystem::mediaNamespace('stories/videos'))
                    ->upload($videoNewAbsLocalPath);

                $metadata = $frameMedia->metadata ?? [];
                $metadata['processing_progress'] = 100;
                $metadata['processing_state'] = 'processed';
                $metadata['processing_updated_at'] = now()->toIso8601String();
                $metadata['processed_at'] = now()->toIso8601String();

                $frameMedia->source_path = $videoData['video_path'];
                $frameMedia->status = MediaStatus::PROCESSED;
                $frameMedia->metadata = $metadata;
                $frameMedia->save();

                $this->frameData->duration_seconds = $clipDurationSeconds;
                $this->frameData->status = StoryStatus::ACTIVE;

                $this->frameData->save();

                $fileDeleteService->setStorageDisk('local')->deleteFile($videoTempOldPath);
                $fileDeleteService->setStorageDisk('local')->deleteFile($videoTempNewPath);
            }
        }

        catch (Exception $e) {
            $this->updateProcessingProgress($frameMedia, 100, 'failed');

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

    private function updateProcessingProgress($frameMedia, int $progress, string $state): void
    {
        if(empty($frameMedia)) {
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
}
