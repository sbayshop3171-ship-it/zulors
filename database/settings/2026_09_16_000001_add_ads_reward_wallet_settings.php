<?php

use Illuminate\Support\Facades\DB;
use Spatie\LaravelSettings\Migrations\SettingsMigration;

return new class extends SettingsMigration
{
    public function up(): void
    {
        DB::transaction(function () {
            $this->migrator->add('wallet.ads_reward_enabled', true);
            $this->migrator->add('wallet.ads_reward_monthly_amount', 300);
            $this->migrator->add('wallet.ads_reward_reset_day', 1);
        });
    }
};
