<?php

namespace App\Services\Filesystem\FFMpeg;

use FFMpeg\FFMpeg;
use FFMpeg\FFProbe;

class FFMpegService
{
    protected $ffmpeg;
    protected $ffprobe;

    public function __construct()
    {
        $this->initializeFFmpeg();
    }

    /**
     * Initialize FFmpeg and FFprobe instances with configuration.
     *
     * @return void
     * @throws RuntimeException
     */
    private function initializeFFmpeg()
    {

        ini_set('memory_limit', '512M');

        $ffmpegPath = $this->resolveBinaryPath(config('ffmpeg.ffmpeg_path'), 'ffmpeg');
        $ffprobePath = $this->resolveBinaryPath(config('ffmpeg.ffprobe_path'), 'ffprobe');
        $temporaryDirectory = $this->resolveTemporaryDirectory(config('ffmpeg.temporary_directory'));
        
        $this->ffmpeg = FFMpeg::create([
            'ffmpeg.binaries' => $ffmpegPath,
            'ffprobe.binaries' => $ffprobePath,
            'timeout' => config('ffmpeg.timeout'),
            'ffmpeg.threads' => config('ffmpeg.threads'),
            'temporary_directory' => $temporaryDirectory
        ]);

        $this->ffprobe = FFProbe::create([
            'ffprobe.binaries' => $ffprobePath
        ]);

        $this->ffmpeg->setFFProbe($this->ffprobe);
    }

    private function resolveBinaryPath(?string $configuredPath, string $binaryName): string
    {
        if($configuredPath && is_executable($configuredPath)) {
            return $configuredPath;
        }

        $pathFromEnvironment = trim((string) shell_exec('command -v ' . escapeshellarg($binaryName) . ' 2>/dev/null'));

        if($pathFromEnvironment && is_executable($pathFromEnvironment)) {
            return $pathFromEnvironment;
        }

        $bundledPath = storage_path("app/bin/ffmpeg-darwin-x64/{$binaryName}");

        if(is_executable($bundledPath)) {
            return $bundledPath;
        }

        return (string) $configuredPath;
    }

    private function resolveTemporaryDirectory(?string $configuredDirectory): string
    {
        if($configuredDirectory && is_dir($configuredDirectory) && is_writable($configuredDirectory)) {
            return $configuredDirectory;
        }

        $fallbackDirectory = storage_path('app/tmp/ffmpeg');

        if(! is_dir($fallbackDirectory)) {
            mkdir($fallbackDirectory, 0755, true);
        }

        return $fallbackDirectory;
    }

    /**
     * Get the FFmpeg instance.
     *
     * @return FFMpeg
     */
    public function getFFMpeg()
    {
        return $this->ffmpeg;
    }

    /**
     * Get the FFprobe instance.
     *
     * @return FFProbe
     */
    public function getFFProbe()
    {
        return $this->ffprobe;
    }
}
