<?php

use Illuminate\Support\Facades\DB;
use Spatie\LaravelSettings\Migrations\SettingsMigration;

return new class extends SettingsMigration
{
    public function up(): void
    {
        DB::transaction(function () {
            $this->migrator->add('acquiring.paypal_name', 'PayPal');
            $this->migrator->add('acquiring.paypal_status', false);
            $this->migrator->add('acquiring.paypal_client_id', '');
            $this->migrator->add('acquiring.paypal_secret_key', '');
            $this->migrator->add('acquiring.paypal_mode', 'sandbox');
            $this->migrator->add('acquiring.paypal_webhook', '');
        });
    }
};
