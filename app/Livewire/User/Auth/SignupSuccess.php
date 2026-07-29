<?php

namespace App\Livewire\User\Auth;

use Livewire\Component;
use Illuminate\Support\Facades\Mail;
use App\Mail\User\Auth\VerifyEmailMail;

class SignupSuccess extends Component
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
        return view('livewire.user.auth.signup-success');
    }

    public function submitForm()
    {
        if($this->confirmationData) {
            if(empty($this->emailResendTimeout) || $this->emailResendTimeout <= now()) {
                Mail::to($this->confirmationData->email)->queue(new VerifyEmailMail([
                    'link' => route('user.auth.confirm-signup', ['token' => $this->confirmationData->token]),
                    'title' => __('auth.hi_there')
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
