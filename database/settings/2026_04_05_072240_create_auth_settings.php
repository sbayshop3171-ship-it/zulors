<?php

use Illuminate\Support\Facades\DB;
use Spatie\LaravelSettings\Migrations\SettingsMigration;

return new class extends SettingsMigration
{
    public function up(): void
    {
        DB::transaction(function () {
            $this->migrator->add('auth.captcha_enabled', false);
            $this->migrator->add('auth.registration_enabled', true);
            $this->migrator->add('auth.login_enabled', true);
            $this->migrator->add('auth.reg_verification_enabled', false);
            $this->migrator->add('auth.reg_verification_type', 'email');
            $this->migrator->add('auth.switch_account_enabled', false);
            $this->migrator->add('auth.link_accounts_enabled', false);
        });
    }
};
