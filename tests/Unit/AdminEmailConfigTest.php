<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Livewire\Admin\Config\Email;

class AdminEmailConfigTest extends TestCase
{
    public function test_brevo_preset_fills_smtp_settings_without_placeholder_credentials(): void
    {
        config(['app.url' => 'https://zulors.com']);

        $component = new Email();
        $component->formData = [
            'transport' => 'log',
            'host' => 'localhost',
            'port' => 2525,
            'timeout' => 60,
            'username' => 'username',
            'password' => 'password',
            'encryption' => 'tls',
            'from_address' => 'noreply@example.com',
            'from_name' => 'Zulors',
            'local_domain' => 'localhost',
        ];

        $component->applyBrevoPreset();

        $this->assertSame('smtp', $component->formData['transport']);
        $this->assertSame('smtp-relay.brevo.com', $component->formData['host']);
        $this->assertSame(587, $component->formData['port']);
        $this->assertSame('tls', $component->formData['encryption']);
        $this->assertSame('zulors.com', $component->formData['local_domain']);
        $this->assertSame('noreply@zulors.com', $component->formData['from_address']);
        $this->assertSame('', $component->formData['username']);
        $this->assertSame('', $component->formData['password']);
    }
}
