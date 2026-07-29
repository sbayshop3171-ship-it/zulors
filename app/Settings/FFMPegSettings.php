<?php

namespace App\Settings;

use Spatie\LaravelSettings\Settings;

class FFMPegSettings extends Settings
{
    public string $ffmpeg_path;
    public string $ffprobe_path;
    public int $timeout;
    public int $threads;
    public string $temporary_directory;

    public static function group(): string
    {
        return 'ffmpeg';
    }
}
