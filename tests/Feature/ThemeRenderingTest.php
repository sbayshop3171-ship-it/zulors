<?php

namespace Tests\Feature;

use Tests\TestCase;

class ThemeRenderingTest extends TestCase
{
    public function test_plain_dark_theme_cookie_controls_auth_layout_initial_render(): void
    {
        $this->withUnencryptedCookie('theme', 'dark')
            ->withUnencryptedCookie('theme_runtime', 'dark')
            ->get(route('user.auth.signup'))
            ->assertOk()
            ->assertSee('data-theme="dark"', false)
            ->assertSee('content="#111111"', false)
            ->assertSee('content="dark light"', false);
    }

    public function test_plain_dark_theme_cookie_controls_document_layout_initial_render(): void
    {
        $this->withUnencryptedCookie('theme', 'dark')
            ->withUnencryptedCookie('theme_runtime', 'dark')
            ->get(route('document.about.index'))
            ->assertOk()
            ->assertSee('data-theme="dark"', false)
            ->assertSee('content="#111111"', false)
            ->assertSee('content="dark light"', false);
    }
}
