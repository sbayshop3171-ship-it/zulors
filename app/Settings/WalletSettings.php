<?php

namespace App\Settings;

use Spatie\LaravelSettings\Settings;

class WalletSettings extends Settings
{
    public string $name;
    public bool $enabled;
    public string $about_link;
    public string $wallet_number_prefix;
    public float $default_balance;
    public float $deposit_min_amount;
    public float $deposit_max_amount;
    public float $transfer_min_amount;
    public float $transfer_max_amount;
    public float $commission_deposit;
    public float $commission_transfer;
    public float $commission_withdraw;
    public float $withdraw_min_amount;
    public float $withdraw_max_amount;
    public string $cashout_methods;

    public static function group(): string
    {
        return 'wallet';
    }
}
