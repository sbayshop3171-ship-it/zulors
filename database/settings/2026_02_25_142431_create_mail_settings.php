<?php

use Illuminate\Support\Facades\DB;
use Spatie\LaravelSettings\Migrations\SettingsMigration;

return new class extends SettingsMigration
{
    public function up(): void
    {
        DB::transaction(function () {
            $this->migrator->add('mail.transport', env('MAIL_MAILER', 'smtp'));
            $this->migrator->add('mail.host', env('MAIL_HOST', 'localhost'));
            $this->migrator->add('mail.port', (int) env('MAIL_PORT', 2525));
            $this->migrator->add('mail.timeout', (int) env('MAIL_TIMEOUT', 60));
            $this->migrator->add('mail.username', env('MAIL_USERNAME', 'username'));
            $this->migrator->add('mail.password', env('MAIL_PASSWORD', 'password'));
            $this->migrator->add('mail.encryption', env('MAIL_SCHEME', 'tls'));
            $this->migrator->add('mail.from_address', env('MAIL_FROM_ADDRESS', 'noreply@example.com'));
            $this->migrator->add('mail.from_name', env('MAIL_FROM_NAME', 'Example'));
            $this->migrator->add('mail.local_domain', parse_url(env('APP_URL', 'http://localhost'), PHP_URL_HOST));
        });
    }
};
