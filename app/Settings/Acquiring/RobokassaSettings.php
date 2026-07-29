<?php

namespace App\Settings\Acquiring;

class RobokassaSettings extends Acquiring
{
    public string $rk_name;
    public bool $rk_status;
    public string $rk_merchant_login;
    public string $rk_pass_one;
    public string $rk_pass_two;
    public string $rk_currency;
    public string $rk_mode;
    public string $rk_webhook;

    public function getDriver(): string
    {
        return 'robokassa';
    }

    public function getLogo(): string
    {
        return $this->logoPath('robokassa');
    }

    public function getRedirectRoute(): string
    {
        return 'callback.robokassa.success';
    }

    public function getCancelRoute(): string
    {
        return 'callback.robokassa.cancel';
    }
}
