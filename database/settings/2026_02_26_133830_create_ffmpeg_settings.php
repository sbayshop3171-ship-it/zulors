<?php

use Illuminate\Support\Facades\DB;
use Spatie\LaravelSettings\Migrations\SettingsMigration;

return new class extends SettingsMigration
{
    public function up(): void
    {
        DB::transaction(function () {
            $this->migrator->add('ffmpeg.ffmpeg_path', '/usr/bin/ffmpeg');
            $this->migrator->add('ffmpeg.ffprobe_path', '/usr/bin/ffprobe');
            $this->migrator->add('ffmpeg.timeout', 3600);
            $this->migrator->add('ffmpeg.threads', 12);
            $this->migrator->add('ffmpeg.temporary_directory', '/var/ffmpeg-tmp');
        });
    }
};
