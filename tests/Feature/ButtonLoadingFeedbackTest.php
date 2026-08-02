<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Blade;
use Tests\TestCase;

class ButtonLoadingFeedbackTest extends TestCase
{
    public function test_pill_submit_buttons_render_loading_feedback(): void
    {
        $html = Blade::render('<x-ui.buttons.pill type="submit" btnText="Continue" />');

        $this->assertStringContainsString('wire:loading.attr="disabled"', $html);
        $this->assertStringContainsString('wire:loading.remove', $html);
        $this->assertStringContainsString('wire:loading.flex', $html);
        $this->assertStringContainsString('colibri-primary-animation', $html);
    }

    public function test_pill_wire_click_buttons_target_their_own_action(): void
    {
        $html = Blade::render('<x-ui.buttons.pill wire:click="resendOtp" btnText="Resend code" />');

        $this->assertStringContainsString('wire:loading.attr="disabled"', $html);
        $this->assertStringContainsString('wire:target="resendOtp"', $html);
        $this->assertStringContainsString('wire:loading.flex', $html);
        $this->assertStringContainsString('colibri-primary-animation', $html);
    }

    public function test_icon_wire_click_buttons_render_loading_feedback(): void
    {
        $html = Blade::render('<x-ui.buttons.icon wire:click="downloadBackup" iconName="download-01" iconType="line" />');

        $this->assertStringContainsString('wire:loading.attr="disabled"', $html);
        $this->assertStringContainsString('wire:target="downloadBackup"', $html);
        $this->assertStringContainsString('wire:loading.flex', $html);
        $this->assertStringContainsString('colibri-primary-animation', $html);
    }
}
