<?php

use Illuminate\Support\Facades\DB;
use Spatie\LaravelSettings\Migrations\SettingsMigration;

return new class extends SettingsMigration
{
    public function up(): void
    {
        DB::transaction(function () {
            $this->migrator->add('acquiring.stripe_name', 'Stripe');
            $this->migrator->add('acquiring.stripe_status', false);
            $this->migrator->add('acquiring.stripe_secret_key', '');
            $this->migrator->add('acquiring.stripe_public_key', '');
            $this->migrator->add('acquiring.stripe_webhook_tolerance', 300);
            $this->migrator->add('acquiring.stripe_webhook_secret', '');
            $this->migrator->add('acquiring.stripe_payment_method_types', 'card, paypal, link');
        });
    }
};
