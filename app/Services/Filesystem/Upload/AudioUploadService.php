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

class AudioUploadService extends AbstractUploadService
{
    use ThrowsUploadExceptions, ThrowsFFMpegExceptions;

    protected $ffmpegService;
    public string $audioDefaultExtension = 'mp3';
    private string $audioTemporaryLocation = 'tmp/audios';

    public function __construct(FFMpegService $ffmpegService)
    {
        $this->ffmpegService = $ffmpegService;
    }

    public function setDefaultExtension(string $extension)
    {
        $this->audioDefaultExtension = $extension;

        return $this;
    }

    public function getFFMpeg()
    {
        return $this->ffmpegService->getFFMpeg();
    }

    public function getFFProbe()
    {
        return $this->ffmpegService->getFFProbe();
    }

    public function upload(string $audioFileLocalPath)
    {
        try {
            $audioPath = $this->determineStoragePath($this->audioDefaultExtension);

            $resultState = Storage::disk($this->storageDisk)->put($audioPath, file_get_contents($audioFileLocalPath));

            if (! $resultState) {
                $this->makeUploadException("Processed audio uploading to disk ({$this->storageDisk}) failed.");
            }

            unlink($audioFileLocalPath);

            return [
                'disk' => $this->storageDisk,
                'audio_path' => $audioPath
            ];
        }

        catch(Exception $e) {
            $this->makeUploadException($e->getMessage(), $this->storageDisk);
        }
    }

    public function tempSaveLocally(UploadedFile $audioFile): array
    {
        try {
            $audioTempPath = Storage::disk('local')->putFile($this->audioTemporaryLocation, $audioFile);

            if (! $audioTempPath) {
                $this->makeUploadException('Audio upload on disk (local) failed.');
            }

            $audioOriginalDuration = $this->getAudioDuration($audioTempPath);

            return [
                'disk' => $this->storageDisk,
                'audio_path' => $audioTempPath,
                'duration' => $audioOriginalDuration
            ];
        }

        catch(Exception $e) {
            $this->makeUploadException($e->getMessage());
        }
    }

    public function getAudioDuration(string $audioLocalPath)
    {
        $retries = 5;

        try {
            return retry($retries, function() use ($audioLocalPath) {

                $ffprobe = $this->getFFProbe();
                $durationInSeconds = $ffprobe->format(storage_local_path($audioLocalPath))->get('duration', 0);

                return parse_duration($durationInSeconds);

            }, 1000);
        }

        catch (Exception $e) {
            $this->makeFFMpegException("FFprobe failed to get audio duration after {$retries} attempts. {$e->getMessage()}");
        }
    }

    public function generateAudioTemporaryFilePath($audioExtension = null)
    {
        $uuid = Str::uuid();

        return "$this->audioTemporaryLocation/{$uuid}.{$audioExtension}";
    }
}
