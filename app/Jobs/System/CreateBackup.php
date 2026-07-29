<?php

namespace App\Jobs\System;

use Exception;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Artisan;

class CreateBackup implements ShouldQueue
{
    use Queueable;

    public $timeout = (60 * 180); // 3 hours

    public function handle(): void
    {
        try {
            Artisan::call('backup:run');
        } catch (Exception $th) {
            $this->fail($th->getMessage());
        } finally {
            cache()->forget('system:backup:processing');
        }
    }
}
