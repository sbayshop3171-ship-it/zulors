<?php

namespace App\Settings;

use Spatie\LaravelSettings\Settings;

class BrandSettings extends Settings
{
    public bool $dark_theme_enabled;
    public string $default_theme;
    public bool $images_watermark_enabled;
    public bool $videos_watermark_enabled;

    public static function group(): string
    {
        return 'brand';
    }
}
