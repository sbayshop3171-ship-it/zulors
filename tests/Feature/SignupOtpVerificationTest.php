<?php

namespace Tests\Feature;

use App\Livewire\User\Auth\SignupSuccess;
use App\Mail\User\Auth\VerifyEmailMail;
use App\Models\EmailConfirmation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;

class SignupOtpVerificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_signup_success_requires_the_correct_unexpired_otp_code(): void
    {
        $confirmation = $this->createConfirmation([
            'code' => '1234',
            'code_expires_at' => now()->addMinutes(10),
        ]);

        Livewire::test(SignupSuccess::class, [
            'confirmationData' => $confirmation,
        ])
            ->set('otpCode', '9999')
            ->call('verifyOtp')
            ->assertHasErrors(['otpCode']);

        Livewire::test(SignupSuccess::class, [
            'confirmationData' => $confirmation,
        ])
            ->set('otpCode', '12-34')
            ->call('verifyOtp')
            ->assertRedirect(route('user.auth.confirm-signup', ['token' => $confirmation->token]));
    }

    public function test_signup_success_rejects_expired_otp_codes(): void
    {
        $confirmation = $this->createConfirmation([
            'code' => '1234',
            'code_expires_at' => now()->subMinute(),
        ]);

        Livewire::test(SignupSuccess::class, [
            'confirmationData' => $confirmation,
        ])
            ->set('otpCode', '1234')
            ->call('verifyOtp')
            ->assertHasErrors(['otpCode']);
    }

    public function test_signup_success_can_resend_a_fresh_otp_code(): void
    {
        Mail::fake();

        $confirmation = $this->createConfirmation([
            'code' => '1234',
            'code_expires_at' => now()->addMinutes(10),
        ]);

        Livewire::test(SignupSuccess::class, [
            'confirmationData' => $confirmation,
        ])
            ->call('resendOtp')
            ->assertSet('emailResent', true);

        $confirmation->refresh();

        $this->assertNotSame('1234', $confirmation->code);
        $this->assertFalse($confirmation->otpCodeExpired());

        Mail::assertQueued(VerifyEmailMail::class, function (VerifyEmailMail $mail) use ($confirmation): bool {
            return $mail->hasTo($confirmation->email) && $mail->data['code'] === $confirmation->code;
        });
    }

    private function createConfirmation(array $overrides = []): EmailConfirmation
    {
        return EmailConfirmation::create(array_merge([
            'email' => 'otp-user@example.com',
            'token' => (string) Str::uuid(),
        ], $overrides));
    }
}
