<?php

namespace App\Settings;

use Spatie\LaravelSettings\Settings;

class MailSettings extends Settings
{
    public string $transport;
    public string $host;
    public int $port;
    public ?int $timeout;
    public string $username;
    public string $password;
    public string $encryption;
    public string $from_address;
    public string $from_name;
    public string $local_domain;

    public static function group(): string
    {
        return 'mail';
    }
}
