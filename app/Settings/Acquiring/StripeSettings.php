<?php

namespace App\Settings\Acquiring;

class StripeSettings extends Acquiring
{
    public string $stripe_name;
    public bool $stripe_status;
    public string $stripe_secret_key;
    public string $stripe_public_key;
    public string $stripe_webhook_tolerance;
    public string $stripe_webhook_secret;
    public string $stripe_payment_method_types;

    public function getDriver(): string
    {
        return 'stripe';
    }

    public function getLogo(): string
    {
        return $this->logoPath('stripe');
    }

    public function getRedirectRoute(): string
    {
        return 'callback.stripe.success';
    }

    public function getCancelRoute(): string
    {
        return 'callback.stripe.cancel';
    }
}
