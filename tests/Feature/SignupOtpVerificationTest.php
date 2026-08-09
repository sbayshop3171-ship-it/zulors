<?php

namespace Tests\Feature;

use App\Jobs\User\Auth\SendSignupOtpEmail;
use App\Livewire\User\Auth\Signup;
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

    public function test_signup_submission_sends_the_same_otp_code_saved_for_verification(): void
    {
        Mail::fake();

        config([
            'features.registration.enabled' => true,
            'features.reg_verification.enabled' => true,
        ]);

        Livewire::test(Signup::class)
            ->set('emailAddress', 'new-otp-user@example.test')
            ->call('submitForm');

        $confirmation = EmailConfirmation::query()
            ->where('email', 'new-otp-user@example.test')
            ->firstOrFail();

        Mail::assertSent(VerifyEmailMail::class, function (VerifyEmailMail $mail) use ($confirmation): bool {
            return $mail->hasTo($confirmation->email) && $mail->data['code'] === $confirmation->code;
        });
    }

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

        Mail::assertSent(VerifyEmailMail::class, function (VerifyEmailMail $mail) use ($confirmation): bool {
            return $mail->hasTo($confirmation->email) && $mail->data['code'] === $confirmation->code;
        });
    }

    public function test_signup_otp_email_job_uses_the_current_saved_code_when_it_runs(): void
    {
        Mail::fake();

        $confirmation = $this->createConfirmation([
            'code' => '1111',
            'code_expires_at' => now()->addMinutes(10),
        ]);

        $job = new SendSignupOtpEmail($confirmation->id, $confirmation->token);

        $confirmation->forceFill([
            'code' => '2222',
            'code_expires_at' => now()->addMinutes(10),
        ])->save();

        $job->handle();

        Mail::assertSent(VerifyEmailMail::class, function (VerifyEmailMail $mail) use ($confirmation): bool {
            return $mail->hasTo($confirmation->email) && $mail->data['code'] === '2222';
        });

        Mail::assertNotSent(VerifyEmailMail::class, function (VerifyEmailMail $mail): bool {
            return $mail->data['code'] === '1111';
        });
    }

    public function test_signup_otp_email_job_refreshes_expired_code_before_sending(): void
    {
        Mail::fake();

        $confirmation = $this->createConfirmation([
            'code' => '1111',
            'code_expires_at' => now()->subMinute(),
        ]);

        $job = new SendSignupOtpEmail($confirmation->id, $confirmation->token);

        $job->handle();

        $confirmation->refresh();

        $this->assertNotSame('1111', $confirmation->code);
        $this->assertFalse($confirmation->otpCodeExpired());

        Mail::assertSent(VerifyEmailMail::class, function (VerifyEmailMail $mail) use ($confirmation): bool {
            return $mail->hasTo($confirmation->email) && $mail->data['code'] === $confirmation->code;
        });
    }

    public function test_signup_otp_email_job_does_not_send_deleted_confirmation_code(): void
    {
        Mail::fake();

        $confirmation = $this->createConfirmation([
            'code' => '3333',
            'code_expires_at' => now()->addMinutes(10),
        ]);

        $job = new SendSignupOtpEmail($confirmation->id, $confirmation->token);

        $confirmation->delete();

        $job->handle();

        Mail::assertNothingSent();
    }

    private function createConfirmation(array $overrides = []): EmailConfirmation
    {
        return EmailConfirmation::create(array_merge([
            'email' => 'otp-user@example.com',
            'token' => (string) Str::uuid(),
        ], $overrides));
    }
}
