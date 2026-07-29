<?php

use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;
use Spatie\LaravelSettings\Migrations\SettingsMigration;

return new class extends SettingsMigration
{
    public function up(): void
    {
        DB::transaction(function () {
            $this->migrator->add('app.name', 'Zulors');
            $this->migrator->add('app.description', 'Zulors Web Application');
            $this->migrator->add('app.keywords', 'social network, social media, news platform, news website');
            $this->migrator->add('app.documentation_url', 'https://docs.zulors.com');
            $this->migrator->add('app.timezone', 'UTC');
            $this->migrator->add('app.locale', 'en');
            $this->migrator->add('app.fallback_locale', 'en');
            $this->migrator->add('app.faker_locale', 'en_US');
            $this->migrator->add('app.admin_locale', 'en');
            $this->migrator->add('app.admin_prefix', 'admin');
            $this->migrator->add('app.hide_sensitive_data', false);

            $this->migrator->add('app.salt', Str::random(32));
            $this->migrator->add('app.api_key', Str::random(64));
            $this->migrator->add('app.version', '2.1.0');
            $this->migrator->add('app.default_currency', 'USD');
            $this->migrator->add('app.mobile_app_android_link', '');
            $this->migrator->add('app.mobile_app_ios_link', '');
            $this->migrator->add('app.pwa_enabled', true);
        });
    }
};
