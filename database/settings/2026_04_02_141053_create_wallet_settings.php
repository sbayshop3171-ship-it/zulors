<?php

use Illuminate\Support\Facades\DB;
use Spatie\LaravelSettings\Migrations\SettingsMigration;

return new class extends SettingsMigration
{
    public function up(): void
    {
        DB::transaction(function () {
            $this->migrator->add('wallet.name', 'ZulorsPay');
            $this->migrator->add('wallet.enabled', true);
            $this->migrator->add('wallet.about_link', '#');
            $this->migrator->add('wallet.wallet_number_prefix', 'ZLR');
            $this->migrator->add('wallet.default_balance', 0);
            $this->migrator->add('wallet.deposit_min_amount', 10);
            $this->migrator->add('wallet.deposit_max_amount', 1000000);
            $this->migrator->add('wallet.transfer_min_amount', 10);
            $this->migrator->add('wallet.transfer_max_amount', 1000000);
            $this->migrator->add('wallet.commission_deposit', 3);
            $this->migrator->add('wallet.commission_transfer', 1);
            $this->migrator->add('wallet.commission_withdraw', 3);
            $this->migrator->add('wallet.withdraw_min_amount', 10);
            $this->migrator->add('wallet.withdraw_max_amount', 1000000);
            $this->migrator->add('wallet.cashout_methods', 'Bank Transfer, PayPal, Stripe');
        });
    }
};
