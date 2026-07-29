<?php
namespace App\Traits\Services\Filesystem;

use Exception;
use Illuminate\Support\Facades\Log;

trait ThrowsFFMpegExceptions
{
    protected function makeFFMpegException(string $errorMessage)
    {
        Log::error($errorMessage);

        throw new Exception($errorMessage);
    }
}
