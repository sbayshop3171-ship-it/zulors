<?php

namespace App\Jobs\User\Auth;

use App\Mail\User\Auth\VerifyEmailMail;
use App\Models\EmailConfirmation;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Mail;

class SendSignupOtpEmail implements ShouldQueue
{
    use Queueable;

    public function __construct(
        private int $confirmationId,
        private string $token
    ) {}

    public function handle(): void
    {
        $confirmation = EmailConfirmation::query()
            ->whereKey($this->confirmationId)
            ->where('token', $this->token)
            ->first();

        if(empty($confirmation)) {
            return;
        }

        if(empty($confirmation->code)) {
            $confirmation->refreshOtpCode();
        }

        if($confirmation->otpCodeExpired()) {
            return;
        }

        Mail::to($confirmation->email)->send(new VerifyEmailMail([
            'link' => route('user.auth.confirm-signup', ['token' => $confirmation->token]),
            'code' => $confirmation->code,
            'title' => __('auth.hi_there')
        ]));
    }
}
