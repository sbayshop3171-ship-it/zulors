<?php

namespace App\Settings\Acquiring;

class PaypalSettings extends Acquiring
{
    public string $paypal_name;
    public bool $paypal_status;
    public string $paypal_client_id;
    public string $paypal_secret_key;
    public string $paypal_mode;
    public string $paypal_webhook;

    public function getDriver(): string
    {
        return 'paypal';
    }

    public function getLogo(): string
    {
        return $this->logoPath('paypal');
    }

    public function getRedirectRoute(): string
    {
        return 'callback.paypal.success';
    }

    public function getCancelRoute(): string
    {
        return 'callback.paypal.cancel';
    }
}
