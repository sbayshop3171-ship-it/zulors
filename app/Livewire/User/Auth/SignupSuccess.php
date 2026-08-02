<?php

namespace App\Livewire\User\Auth;

use Livewire\Component;
use App\Jobs\User\Auth\SendSignupOtpEmail;

class SignupSuccess extends Component
{
    public $confirmationData;
    public $emailResent;
    public $emailResendTimeout;
    public string $otpCode = '';

    public function mount()
    {
        $this->emailResendTimeout = session()->get('signupOtpResendTime', null);

        if($this->confirmationData && empty($this->confirmationData->code)) {
            $this->confirmationData->refreshOtpCode();
        }
    }

    public function render()
    {
        return view('livewire.user.auth.signup-success');
    }

    public function submitForm()
    {
        return $this->verifyOtp();
    }

    public function verifyOtp()
    {
        if(! $this->confirmationData) {
            abort(500);
        }

        $this->otpCode = preg_replace('/\D+/', '', $this->otpCode);

        $this->validate(rules: [
            'otpCode' => ['required', 'digits:4'],
        ], attributes: [
            'otpCode' => __('auth.otp_code'),
        ]);

        $this->confirmationData->refresh();

        if($this->confirmationData->otpCodeExpired()) {
            $this->addError('otpCode', __('auth.otp_code_expired'));

            return false;
        }

        if(! $this->confirmationData->otpCodeMatches($this->otpCode)) {
            $this->addError('otpCode', __('auth.otp_code_invalid'));

            return false;
        }

        return $this->redirect(route('user.auth.confirm-signup', ['token' => $this->confirmationData->token]));
    }

    public function resendOtp()
    {
        if($this->confirmationData) {
            if(empty($this->emailResendTimeout) || $this->emailResendTimeout <= now()) {
                $this->confirmationData->refreshOtpCode();

                SendSignupOtpEmail::dispatch(
                    $this->confirmationData->id,
                    $this->confirmationData->token
                );

                $this->emailResent = true;

                $this->emailResendTimeout = now()->addMinute();

                session()->put('signupOtpResendTime', $this->emailResendTimeout);
            }
            else{
                $this->addError('resend-timeout', __('auth.resend_otp_error'));
            }
        }

        else{
            abort(500);
        }
    }
}
