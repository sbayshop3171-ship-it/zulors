<?php

namespace App\Providers;

use Illuminate\Support\Facades\Config;
use Illuminate\Support\ServiceProvider;

class FileStorageDisksProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        Config::set('filesystems.disks', array_merge(config('filesystems.system_disks'), require(var_path('config/filesystems/disks.php'))));
    }
}
