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
        try {
            $videoUploadService = app(VideoUploadService::class);
            $fileDeleteService = app(FileDeleteService::class);
            $frameMedia = $this->frameData->media->first();

            $videoTempOldPath = $frameMedia->source_path;

            $videoTempNewPath = $videoUploadService->generateVideoTemporaryFilePath("processed.{$videoUploadService->videoDefaultExtension}");

            $videoOldAbsLocalPath = storage_local_path($videoTempOldPath);
            $videoNewAbsLocalPath = storage_local_path($videoTempNewPath);

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
            $video = $ffmpeg->open($videoOldAbsLocalPath);

            $video->filters()->clip(TimeCode::fromSeconds(0), TimeCode::fromSeconds(config('story.video_clip_size')));

            $video->filters()->resize(new Dimension(1080, 1920), ResizeFilter::RESIZEMODE_INSET)->synchronize();

            $video->filters()->pad(new Dimension(1080, 1920), function ($width, $height) {
                return [0, ($height - 1920) / 2];
            })->synchronize();

            $video->save($format, $videoNewAbsLocalPath);

            if(file_exists($videoNewAbsLocalPath)) {
                $videoData = $videoUploadService
                    ->setStorageDisk($frameMedia->disk)
                    ->setNamespace(Filesystem::mediaNamespace('stories/videos'))
                    ->upload($videoNewAbsLocalPath);

                $frameMedia->source_path = $videoData['video_path'];
                $frameMedia->status = MediaStatus::PROCESSED;
                $frameMedia->save();

                $this->frameData->duration_seconds = (intval($this->frameData->duration_seconds) > config('story.video_clip_size')) ? config('story.video_clip_size') : intval($this->frameData->duration_seconds);
                $this->frameData->status = StoryStatus::ACTIVE;

                $this->frameData->save();

                $fileDeleteService->setStorageDisk('local')->deleteFile($videoTempOldPath);
                $fileDeleteService->setStorageDisk('local')->deleteFile($videoTempNewPath);
            }
        }

        catch (Exception $e) {
            Log::error('Story video processing failed after 5 attempts. Error: ' . $e->getMessage());

            $this->fail();
        }
    }

    public function tries(): int
    {
        return 5;
    }
}
