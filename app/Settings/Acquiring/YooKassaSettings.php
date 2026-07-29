<?php

namespace App\Settings\Acquiring;

class YooKassaSettings extends Acquiring
{
    public string $yk_name;
    public bool $yk_status;
    public string $yk_shop_id;
    public string $yk_secret_key;
    public string $yk_webhook;
    public string $yk_currency;

    public function getDriver(): string
    {
        return 'yoo_kassa';
    }

    public function getLogo(): string
    {
        return $this->logoPath('yookassa');
    }

    public function getRedirectRoute(): string
    {
        return 'callback.yoo_kassa.success';
    }

    public function getCancelRoute(): string
    {
        return 'callback.yoo_kassa.cancel';
    }
}
