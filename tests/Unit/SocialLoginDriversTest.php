<?php

namespace Tests\Unit;

use App\Support\SocialLoginDrivers;
use Tests\TestCase;

class SocialLoginDriversTest extends TestCase
{
    public function test_public_social_drivers_do_not_expose_credentials(): void
    {
        config([
            'social-login.providers' => [
                'google' => [
                    'enabled' => true,
                    'credentials' => [
                        'client_id' => 'public-client-id',
                        'client_secret' => 'private-client-secret',
                        'redirect' => 'https://example.com/callback',
                    ],
                    'meta' => [
                        'name' => 'Google',
                        'url' => 'social-login.google.redirect',
                        'logo' => 'assets/social-logos/google.png',
                    ],
                ],
                'disabled' => [
                    'enabled' => false,
                    'credentials' => [
                        'client_secret' => 'disabled-secret',
                    ],
                    'meta' => [
                        'name' => 'Disabled',
                        'url' => 'social-login.disabled.redirect',
                        'logo' => 'assets/social-logos/disabled.png',
                    ],
                ],
            ],
        ]);

        $drivers = (new SocialLoginDrivers())->getActivePublicDrivers();
        $encodedDrivers = json_encode($drivers);

        $this->assertSame(['google'], array_keys($drivers));
        $this->assertArrayHasKey('meta', $drivers['google']);
        $this->assertArrayNotHasKey('credentials', $drivers['google']);
        $this->assertStringNotContainsString('private-client-secret', $encodedDrivers);
        $this->assertStringNotContainsString('disabled-secret', $encodedDrivers);
    }
}
