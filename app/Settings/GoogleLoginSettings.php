<?php

namespace App\Settings;

use Spatie\LaravelSettings\Settings;

class GoogleLoginSettings extends Settings
{
    public bool $enabled;
    public string $client_id;
    public string $client_secret;

    public static function group(): string
    {
        return 'google_login';
    }

    public function getName(): string
    {
        return 'Google';
    }

    public function getCallbackPath(): string
    {
        return url('social-login/callback/google');
    }

    public function getRedirectRoute(): string
    {
        return 'social-login.google.redirect';
    }

    public function getLogo(): string
    {
        return 'assets/social-logos/google.png';
    }
}
