<?php

namespace App\Settings;

use Spatie\LaravelSettings\Settings;

class AuthSettings extends Settings
{
    public bool $captcha_enabled;
    public bool $registration_enabled;
    public bool $login_enabled;
    public bool $reg_verification_enabled;
    public bool $switch_account_enabled;
    public bool $link_accounts_enabled;
    public string $reg_verification_type;

    public static function group(): string
    {
        return 'auth';
    }
}
