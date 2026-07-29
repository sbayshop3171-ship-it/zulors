<?php

namespace App\Settings;

use Spatie\LaravelSettings\Settings;

class AppSettings extends Settings
{
    public string $name;
    public string $description;
    public string $keywords;
    public string $documentation_url;
    public string $timezone;
    public string $locale;
    public string $fallback_locale;
    public string $faker_locale;
    public string $admin_locale;
    public string $admin_prefix;
    public string $salt;
    public string $api_key;
    public string $version;
    public string $default_currency;
    public string $mobile_app_android_link;
    public string $mobile_app_ios_link;
    public bool $pwa_enabled;
    public bool $hide_sensitive_data;

    public static function group(): string
    {
        return 'app';
    }
}
