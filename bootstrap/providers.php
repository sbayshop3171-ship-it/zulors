<?php

$providers = [
    App\Providers\AppServiceProvider::class,
    App\Providers\AuthServiceProvider::class,
    App\Providers\EventServiceProvider::class,
    App\Providers\FFMpegServiceProvider::class,
    App\Providers\FileStorageDisksProvider::class,
    App\Providers\HorizonServiceProvider::class,
    App\Providers\SettingsServiceProvider::class,
    App\Providers\ViewServiceProvider::class,
    ...require(var_path('providers/index.php')),
];

return $providers;
