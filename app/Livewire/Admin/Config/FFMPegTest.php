<?php

namespace App\Livewire\Admin\Config;

use Livewire\Component;
use Throwable;

class FFMPegTest extends Component
{
    public string $ffmpegOutput;
    public string $ffprobeOutput;
    public string $error;
    public bool $isTested;

    public function mount()
    {
        $this->ffmpegOutput = '';
        $this->ffprobeOutput = '';
        $this->error = '';
        $this->isTested = false;
    }

    public function render()
    {
        return view('livewire.admin.config.ffmpeg-test');
    }

    public function submitForm()
    {
        try {
            $ffmpegPath = config('ffmpeg.ffmpeg_path');
            $ffprobePath = config('ffmpeg.ffprobe_path');

            $this->ffmpegOutput = shell_exec(escapeshellcmd($ffmpegPath) . ' -version 2>&1');
            $this->ffprobeOutput = shell_exec(escapeshellcmd($ffprobePath) . ' -version 2>&1');

            $this->isTested = true;
        } catch (Throwable $th) {
            $this->isTested = false;
        }
    }
}
