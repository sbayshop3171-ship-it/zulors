<?php

namespace Tests\Unit;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class BrevoApiTransportTest extends TestCase
{
    public function test_brevo_api_mailer_posts_transactional_email_payload(): void
    {
        Http::fake([
            'api.brevo.com/v3/smtp/email' => Http::response(['messageId' => '<test@brevo>'], 201),
        ]);

        config([
            'mail.default' => 'brevo_api',
            'mail.mailers.brevo_api' => [
                'transport' => 'brevo_api',
                'key' => 'xkeysib-test',
                'timeout' => 60,
            ],
            'mail.from.address' => 'noreply@zulors.com',
            'mail.from.name' => 'Zulors',
        ]);

        Mail::purge('brevo_api');

        Mail::mailer('brevo_api')->raw('OTP code: 123456', function ($message) {
            $message->to('user@example.com', 'Test User')->subject('Your OTP');
        });

        Http::assertSent(function ($request) {
            return $request->url() === 'https://api.brevo.com/v3/smtp/email'
                && $request->hasHeader('api-key', 'xkeysib-test')
                && $request['sender']['email'] === 'noreply@zulors.com'
                && $request['sender']['name'] === 'Zulors'
                && $request['to'][0]['email'] === 'user@example.com'
                && $request['to'][0]['name'] === 'Test User'
                && $request['subject'] === 'Your OTP'
                && $request['textContent'] === 'OTP code: 123456';
        });
    }
}
