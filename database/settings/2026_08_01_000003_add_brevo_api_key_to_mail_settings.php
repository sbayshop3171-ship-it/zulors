<?php

use Spatie\LaravelSettings\Migrations\SettingsMigration;

return new class extends SettingsMigration
{
    public function up(): void
    {
        $this->migrator->add('mail.brevo_api_key', env('MAIL_BREVO_API_KEY', env('BREVO_API_KEY', '')));
    }
};
