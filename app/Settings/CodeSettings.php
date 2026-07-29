<?php

namespace App\Settings;

use Spatie\LaravelSettings\Settings;

class CodeSettings extends Settings
{
    public string $header_code;
    public string $footer_code;
    public bool $header_code_enabled;
    public bool $footer_code_enabled;

    public static function group(): string
    {
        return 'code';
    }
}
