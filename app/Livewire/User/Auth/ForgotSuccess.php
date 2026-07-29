<?php

namespace App\Livewire\User\Auth;

use App\Models\User;
use Livewire\Component;
use Illuminate\Support\Facades\Mail;
use App\Mail\User\Auth\ResetPasswordMail;

class ForgotSuccess extends Component
{
    public $confirmationData;
    public $emailResent;
    public $emailResendTimeout;

    public function mount()
    {
        $this->emailResendTimeout = session()->get('emailResendTime', null);
    }

    public function render()
    {
        return view('livewire.user.auth.forgot-success');
    }

    public function submitForm()
    {
        if($this->confirmationData) {
            if(empty($this->emailResendTimeout) || $this->emailResendTimeout <= now()) {
                $userData = User::where('email', $this->confirmationData->email)->first();

                Mail::to($this->confirmationData->email)->queue(new ResetPasswordMail([
                    'name' => $userData->name,
                    'link' => route('user.auth.reset', ['token' => $this->confirmationData->token])
                ]));

                $this->emailResent = true;

                $this->emailResendTimeout = now()->addMinutes(30);

                session()->put('emailResendTime', $this->emailResendTimeout);
            }
            else{
                $this->addError('resend-timeout', __('auth.resend_link_error'));
            }
        }

        else{
            abort(500);
        }
    }
}
