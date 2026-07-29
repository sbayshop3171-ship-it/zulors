<?php

namespace App\Livewire\Admin\Config;
use App\Jobs\System\CreateBackup;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Livewire\Component;
use Throwable;

class Backups extends Component
{
    public Collection $backups;
    public string $error = '';
    public bool $hasError = false;
    public bool $isProcessing = false;
    public bool $isDownloading = false;

    public function mount()
    {
        try {
            $this->loadBackups();
        } catch (Throwable $th) {
            $this->error = $th->getMessage();
            $this->backups = collect([]);
            $this->hasError = true;
        }
    }

    public function loadBackups()
    {
        $this->backups = collect(Storage::disk('local')->files('backups'))
            ->map(fn ($file) => [
                'name' => basename($file),
                'path' => $file,
                'size' => file_size_format(Storage::disk('local')->size($file)),
                'date' => Carbon::parse(Storage::disk('local')->lastModified($file))->format('d M Y, H:i:s'),
            ])
            ->sortByDesc('date')
            ->values();

        $this->checkBackupProcessing();
    }

    public function createBackup()
    {
        try {
            if($this->isProcessing) {
                $this->error = __('admin/config.validation.backup_processing');
                $this->hasError = true;
            }
            else {
                cache()->forever('system:backup:processing', true);

                CreateBackup::dispatch();

                $this->checkBackupProcessing();
            }

        } catch (Throwable $th) {
            $this->error = $th->getMessage();
            $this->hasError = true;
        }
    }

    public function deleteBackup(string $backupPath)
    {
        try {
            Storage::disk('local')->delete($backupPath);
            $this->backups = $this->backups->filter(fn ($backup) => $backup['path'] !== $backupPath);
        } catch (Throwable $th) {
            $this->error = $th->getMessage();
            $this->hasError = true;
        }
    }

    public function render()
    {
        return view('livewire.admin.config.backups');
    }

    public function downloadBackup(string $backupName)
    {
        return response()->streamDownload(function () use ($backupName) {
            $stream = fopen(Storage::disk('local')->path("backups/{$backupName}"), 'rb');
            fpassthru($stream);
            fclose($stream);
        }, $backupName, [
            'Content-Type' => 'application/zip',
            'Content-Length' => filesize(Storage::disk('local')->path("backups/{$backupName}")),
        ]);
    }

    private function checkBackupProcessing()
    {
        $this->isProcessing = cache()->has('system:backup:processing');
    }
}
