<?php

namespace App\Settings\Acquiring;

use Spatie\LaravelSettings\Settings;

class Acquiring extends Settings
{
    public static function group(): string
    {
        return 'acquiring';
    }

    public function logoPath(string $driver): string
    {
        return "assets/payments/{$driver}.png";
    }

    public function getDefaultRedirectRoute(): string
    {
        return 'user.desktop.index';
    }

    public function getDefaultCancelRoute(): string
    {
        return 'user.desktop.index';
    }
}
