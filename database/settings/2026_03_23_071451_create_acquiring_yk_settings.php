<?php

use Illuminate\Support\Facades\DB;
use Spatie\LaravelSettings\Migrations\SettingsMigration;

return new class extends SettingsMigration
{
    public function up(): void
    {
        DB::transaction(function () {
            $this->migrator->add('acquiring.yk_name', 'YooKassa');
            $this->migrator->add('acquiring.yk_status', false);
            $this->migrator->add('acquiring.yk_shop_id', '');
            $this->migrator->add('acquiring.yk_secret_key', '');
            $this->migrator->add('acquiring.yk_webhook', '');
            $this->migrator->add('acquiring.yk_currency', 'RUB');
        });
    }
};
