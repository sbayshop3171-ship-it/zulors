<?php

namespace App\Support;

class SocialLoginDrivers
{
    protected array $socialLoginOptions;

    public function __construct()
    {
        $this->socialLoginOptions = config('social-login.providers', []) ?: [];
    }

    public function getActiveDrivers():array
    {
        return collect($this->socialLoginOptions)->filter(function($item) {
            return (bool) $item['enabled'] === true;
        })->toArray();
    }

    public function getActivePublicDrivers(): array
    {
        return collect($this->getActiveDrivers())->map(function($item) {
            return [
                'meta' => $item['meta'] ?? [],
            ];
        })->toArray();
    }

    public function getDriver(string $name):array
    {
        return $this->socialLoginOptions[$name] ?? [];
    }
}
