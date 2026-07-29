<?php

namespace App\Livewire\User\Auth;

use App\Models\User;
use Livewire\Component;
use Illuminate\Support\Str;
use App\Models\EmailConfirmation;
use Illuminate\Support\Facades\Mail;
use App\Mail\User\Auth\ResetPasswordMail;

class Forgot extends Component
{
    public string $emailAddress;

    public function render()
    {
        return view('livewire.user.auth.forgot');
    }

    public function submitForm()
    {
        $this->validate(rules: [
            'emailAddress' => ['required', 'string', 'email', 'max:62', 'exists:users,email']
        ], messages: [
            'emailAddress.exists' => __('auth.email_not_found')
        ], attributes: [
            'emailAddress' => __('auth.email')
        ]);

        $emailToken = Str::uuid();

        $userData = User::where('email', $this->emailAddress)->first();

        $emailConfirmation = EmailConfirmation::create([
            'email' => $this->emailAddress,
            'token' => $emailToken
        ]);

        Mail::to($this->emailAddress)->queue(new ResetPasswordMail([
            'name' => $userData->name,
            'link' => route('user.auth.reset', ['token' => $emailToken])
        ]));

        $this->redirect(route('user.auth.forgot-success', ['hashId' => $emailConfirmation->hash_id]));
    }
}
