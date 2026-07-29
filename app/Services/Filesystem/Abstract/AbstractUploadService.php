<?php

namespace App\Services\Filesystem\Abstract;

use Illuminate\Support\Str;
use Illuminate\Http\UploadedFile;

abstract class AbstractUploadService
{
    private string $fileNamespace;

    protected $storageDisk;

    public function determineStoragePath($extension = null)
    {
        $uuid = Str::uuid();

        return "{$this->fileNamespace}/{$uuid}.{$extension}";
    }

    public function determineStorageDirectory($subDirectory = null)
    {
        if ($subDirectory) {
            return "{$this->fileNamespace}/{$subDirectory}";
        }

        return $this->fileNamespace;
    }

    public function setStorageDisk(string $disk)
    {
        $this->storageDisk = $disk;

        return $this;
    }

    public function setNamespace(string $namespace)
    {
        $this->fileNamespace = $namespace;

        return $this;
    }
}
