<?php

namespace App\Livewire\Admin\Config;

use App\Settings\FFMPegSettings;
use App\Support\Views\Flash;
use Livewire\Component;

class FFMPeg extends Component
{
    public array $formData = [];

    public function mount()
    {
        $ffmpegSettings = app(FFMPegSettings::class);

        $this->formData = [
            'ffmpeg_path' => $ffmpegSettings->ffmpeg_path,
            'ffprobe_path' => $ffmpegSettings->ffprobe_path,
            'timeout' => $ffmpegSettings->timeout,
            'threads' => $ffmpegSettings->threads,
            'temporary_directory' => $ffmpegSettings->temporary_directory,
        ];
    }

    public function render()
    {
        return view('livewire.admin.config.ffmpeg');
    }

    public function submitForm()
    {
        $this->validate([
            'formData.ffmpeg_path' => ['required', 'string', 'max:255'],
            'formData.ffprobe_path' => ['required', 'string', 'max:255'],
            'formData.timeout' => ['required', 'integer', 'min:1', 'max:3600'],
            'formData.threads' => ['required', 'integer', 'min:1', 'max:12'],
            'formData.temporary_directory' => ['required', 'string', 'max:255'],
        ], attributes: [
            'formData.ffmpeg_path' => __('admin/config.form.ffmpeg_path'),
            'formData.ffprobe_path' => __('admin/config.form.ffprobe_path'),
            'formData.timeout' => __('admin/config.form.timeout'),
            'formData.threads' => __('admin/config.form.threads'),
            'formData.temporary_directory' => __('admin/config.form.temporary_directory'),
        ]);

        if(! is_executable($this->formData['ffmpeg_path'])) {
            $this->addError('formData.ffmpeg_path', __('admin/config.validation.ffmpeg_path_error'));

            return false;
        }

        else if(! is_executable($this->formData['ffprobe_path'])) {
            $this->addError('formData.ffprobe_path', __('admin/config.validation.ffprobe_path_error'));

            return false;
        }

        else if(! is_dir($this->formData['temporary_directory']) || ! is_writable($this->formData['temporary_directory'])) {
            $this->addError('formData.temporary_directory', __('admin/config.validation.temporary_directory_error'));

            return false;
        }

        $ffmpegSettings = app(FFMPegSettings::class);

        $ffmpegSettings->ffmpeg_path = $this->formData['ffmpeg_path'];
        $ffmpegSettings->ffprobe_path = $this->formData['ffprobe_path'];
        $ffmpegSettings->timeout = $this->formData['timeout'];
        $ffmpegSettings->threads = $this->formData['threads'];
        $ffmpegSettings->temporary_directory = $this->formData['temporary_directory'];

        $ffmpegSettings->save();

        return redirect()->with('flashMessage', (new Flash(content: __('admin/flash.config.settings_success')))->get())->route('admin.config.ffmpeg');
    }
}
