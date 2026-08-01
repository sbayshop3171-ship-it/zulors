<?php

namespace App\Services\Filesystem\Upload;

use Exception;
use Illuminate\Support\Str;
use FFMpeg\Coordinate\TimeCode;
use App\Services\Filesystem\FFMpeg\FFMpegService;
use App\Traits\Services\Filesystem\ThrowsFFMpegExceptions;
use App\Services\Filesystem\Abstract\AbstractFFMpegService;

class VideoThumbnailService extends AbstractFFMpegService
{
    use ThrowsFFMpegExceptions;

    protected $ffmpegService;
    private string $imageTemporaryLocation = 'tmp/images';
    private int $secondsOffset = 0;

    public function __construct(FFMpegService $ffmpegService)
    {
        $this->ffmpegService = $ffmpegService;
    }

    public function setSecondsOffset(int $secondsOffset)
    {
        $this->secondsOffset = $secondsOffset;

        return $this;
    }

    public function getFFMpeg()
    {
        return $this->ffmpegService->getFFMpeg();
    }

    public function generateThumbnail(string $videoLocalPath): string
    {
        try {
            return $this->extractAndTempLocallySaveVideoThumbnail($videoLocalPath);
        }

        catch(Exception $e) {
            $this->makeFFMpegException($e->getMessage());
        }
    }

    private function extractAndTempLocallySaveVideoThumbnail(string $videoLocalPath)
    {
        $retries = 5;

        try {
            return retry($retries, function() use ($videoLocalPath) {
                $tempThumbnailPath = storage_local_path($this->generateImageTemporaryFilePath('jpeg'));
                $tempThumbnailDirname = dirname($tempThumbnailPath);

                if(! $this->ensureWritableDirectory($tempThumbnailDirname)) {
                    $this->makeFFMpegException("FFMpeg temporary thumbnail directory is not writable: {$tempThumbnailDirname}");
                }

                $ffmpeg = $this->getFFMpeg();

                $video = $ffmpeg->open(storage_local_path($videoLocalPath, 'local'));

                $video->frame(TimeCode::fromSeconds($this->secondsOffset))->save($tempThumbnailPath);

                return $tempThumbnailPath;

            }, 1000);
        }

        catch (Exception $e) {
            $this->makeFFMpegException("FFMpeg failed to generate thumbnail after {$retries} attempts. {$e->getMessage()}");
        }
    }

    public function generateImageTemporaryFilePath($imageExtension = null)
    {
        $uuid = Str::uuid();

        return "$this->imageTemporaryLocation/{$uuid}.{$imageExtension}";
    }

    private function ensureWritableDirectory(string $directory): bool
    {
        if(! is_dir($directory)) {
            @mkdir($directory, 0775, true);
        }

        if(is_dir($directory) && ! is_writable($directory)) {
            @chmod($directory, 0775);
        }

        return is_dir($directory) && is_writable($directory);
    }
}
