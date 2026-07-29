<?php

namespace App\Services\Filesystem\Delete;

use Illuminate\Support\Facades\Storage;

class FileDeleteService
{
    private string $disk = 'local';

    public function deleteFile(string $filePath)
    {
        Storage::disk($this->disk)->delete($filePath);
    }

    /**
     * Static setter to set the disk for all operations.
     *
     * @param string $disk
     */
    public function setStorageDisk(string $disk)
    {
        $this->disk = $disk;

        return $this;
    }
}
