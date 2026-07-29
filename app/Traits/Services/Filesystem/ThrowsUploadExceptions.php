<?php
namespace App\Traits\Services\Filesystem;

use Exception;
use Illuminate\Support\Facades\Log;

trait ThrowsUploadExceptions
{
    protected function makeUploadException(string $message)
    {
        $errorMessage = "File upload failed on disk: {$message}";

        Log::error($errorMessage);

        throw new Exception($errorMessage);
    }
}
