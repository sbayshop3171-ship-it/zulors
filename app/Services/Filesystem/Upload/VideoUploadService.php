<?php

namespace App\Services\Filesystem\Upload;

use Exception;
use Illuminate\Support\Str;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use App\Services\Filesystem\FFMpeg\FFMpegService;
use App\Traits\Services\Filesystem\ThrowsFFMpegExceptions;
use App\Traits\Services\Filesystem\ThrowsUploadExceptions;
use App\Services\Filesystem\Abstract\AbstractUploadService;

class VideoUploadService extends AbstractUploadService
{
    use ThrowsUploadExceptions, ThrowsFFMpegExceptions;

    protected $ffmpegService;
    public string $videoDefaultExtension = 'mp4';
    private string $videoTemporaryLocation = 'tmp/videos';

    public function __construct(FFMpegService $ffmpegService)
    {
        $this->ffmpegService = $ffmpegService;
    }

    public function setDefaultExtension(string $extension)
    {
        $this->videoDefaultExtension = $extension;

        return $this;
    }

    public function upload(string $videoFileLocalPath)
    {
        try {
            $videoPath = $this->determineStoragePath($this->videoDefaultExtension);
            $videoStream = fopen($videoFileLocalPath, 'rb');

            if (! is_resource($videoStream)) {
                $this->makeUploadException("Processed video source ({$videoFileLocalPath}) could not be opened.");
            }

            try {
                $resultState = Storage::disk($this->storageDisk)->put($videoPath, $videoStream, $this->getUploadOptions());
            } finally {
                fclose($videoStream);
            }

            if (! $resultState) {
                $this->makeUploadException("Processed video uploading to disk ({$this->storageDisk}) failed.");
            }

            unlink($videoFileLocalPath);

            return [
                'disk' => $this->storageDisk,
                'video_path' => $videoPath,
                'video_size' => Storage::disk($this->storageDisk)->size($videoPath)
            ];
        }

        catch(Exception $e) {
            $this->makeUploadException($e->getMessage());
        }
    }

    public function getFFMpeg()
    {
        return $this->ffmpegService->getFFMpeg();
    }

    public function getFFProbe()
    {
        return $this->ffmpegService->getFFProbe();
    }

    public function tempSaveLocally(UploadedFile $videoFile): array
    {
        $videoTempPath = null;

        try {
            $videoTempPath = Storage::disk('local')->putFile($this->videoTemporaryLocation, $videoFile);

            if (! $videoTempPath) {
                $this->makeUploadException('Video upload on disk (local) failed.');
            }

            $videoOriginalDuration = $this->getVideoDuration($videoTempPath);

            return [
                'disk' => $this->storageDisk,
                'video_path' => $videoTempPath,
                'duration' => parse_duration(intval($videoOriginalDuration)),
                'seconds' => intval($videoOriginalDuration)
            ];
        }

        catch(Exception $e) {
            if($videoTempPath && Storage::disk('local')->exists($videoTempPath)) {
                Storage::disk('local')->delete($videoTempPath);
            }

            $this->makeUploadException($e->getMessage());
        }
    }

    public function getVideoDuration(string $videoLocalPath)
    {
        $retries = 5;

        try {
            return retry($retries, function() use ($videoLocalPath) {
                $ffprobe = $this->getFFProbe();
                $videoLocalAbsolutePath = storage_local_path($videoLocalPath);

                if (! file_exists($videoLocalAbsolutePath)) {
                    $this->makeFFMpegException("The video file does not exist at location: {$videoLocalAbsolutePath}");
                }

                $durationInSeconds = $ffprobe->format($videoLocalAbsolutePath)->get('duration');
                $durationInSeconds = (is_numeric($durationInSeconds)) ? $durationInSeconds : 0;

                return $durationInSeconds;

            }, 1000);
        }

        catch (Exception $e) {
            $this->makeFFMpegException("FFprobe failed to get video duration after {$retries} attempts. {$e->getMessage()}");
        }
    }

    public function generateVideoTemporaryFilePath($videoExtension = null)
    {
        $uuid = Str::uuid();

        return "$this->videoTemporaryLocation/{$uuid}.{$videoExtension}";
    }

    private function getUploadOptions(): array
    {
        $options = [
            'visibility' => 'public',
            'ContentType' => 'video/mp4',
        ];

        if($cacheControl = config('media.cache.control')) {
            $options['CacheControl'] = $cacheControl;
        }

        return $options;
    }
}
