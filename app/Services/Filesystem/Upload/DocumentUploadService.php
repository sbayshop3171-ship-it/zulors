<?php

namespace App\Services\Filesystem\Upload;

use Exception;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use App\Traits\Services\Filesystem\ThrowsUploadExceptions;
use App\Services\Filesystem\Abstract\AbstractUploadService;

class DocumentUploadService extends AbstractUploadService
{
    use ThrowsUploadExceptions;

    public function upload(UploadedFile $file): array
    {
        try {
            $fileDirectory = $this->determineStorageDirectory();

            $filePath = Storage::disk($this->storageDisk)->putFile($fileDirectory, $file);

            if (! $filePath) {
                $this->makeUploadException("Document upload on disk ({$this->storageDisk}) failed.");
            }

            return [
                'disk' => $this->storageDisk,
                'document_path' => $filePath 
            ];
        }

        catch(Exception $e) {
            $this->makeUploadException($e->getMessage());
        }
    }
}
