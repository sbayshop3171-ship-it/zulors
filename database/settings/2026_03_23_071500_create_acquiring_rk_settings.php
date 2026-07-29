<?php

use Illuminate\Support\Facades\DB;
use Spatie\LaravelSettings\Migrations\SettingsMigration;

return new class extends SettingsMigration
{
    public function up(): void
    {
        DB::transaction(function () {
            $this->migrator->add('acquiring.rk_name', 'Robokassa');
            $this->migrator->add('acquiring.rk_status', false);
            $this->migrator->add('acquiring.rk_merchant_login', '');
            $this->migrator->add('acquiring.rk_pass_one', '');
            $this->migrator->add('acquiring.rk_pass_two', '');
            $this->migrator->add('acquiring.rk_currency', 'RUB');
            $this->migrator->add('acquiring.rk_mode', 'sandbox');
            $this->migrator->add('acquiring.rk_webhook', '');
        });
    }
};
