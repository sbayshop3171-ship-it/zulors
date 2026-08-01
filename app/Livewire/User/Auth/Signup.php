<?php

namespace App\Livewire\User\Auth;

use Livewire\Component;
use Illuminate\Support\Str;
use App\Models\EmailConfirmation;
use App\Support\SocialLoginDrivers;
use App\Services\Blacklist\BlacklistService;
use App\Events\User\Auth\UserRegisteredEvent;

class Signup extends Component
{
    public string $emailAddress;
    public $activeSocialDrivers;
    public $showAllSocialOptions = false;

    public function mount(SocialLoginDrivers $socialLoginDrivers)
    {
        $this->activeSocialDrivers = $socialLoginDrivers->getActiveDrivers();
    }

    public function showAllSocialLoginOptions()
    {
        $this->showAllSocialOptions = true;
    }

    public function render()
    {
        return view('livewire.user.auth.signup');
    }

    public function submitForm()
    {
        $this->validate(rules: [
            'emailAddress' => ['required', 'string', 'email', 'max:62', 'unique:users,email']
        ], attributes: [
            'emailAddress' => __('auth.email')
        ]);

        if($this->checkIfEmailBlacklisted()) {
            $this->addError('emailAddress', __('auth.email_blocked'));

            return false;
        }

        // Check if registration is enabled
        if(! config('features.registration.enabled')) {
            $this->addError('emailAddress', __('auth.registration_disabled'));

            return false;
        }

        $emailToken = Str::uuid();

        EmailConfirmation::where('email', $this->emailAddress)->delete();

        $emailConfirmation = EmailConfirmation::create([
            'email' => $this->emailAddress,
            'token' => $emailToken,
            'code' => EmailConfirmation::generateOtpCode(),
            'code_expires_at' => now()->addMinutes(10),
        ]);

        event(new UserRegisteredEvent([
            'email' => $this->emailAddress,
            'link' => route('user.auth.confirm-signup', ['token' => $emailToken]),
            'code' => $emailConfirmation->code,
        ]));

        // Check if registration verification is enabled
        if(config('features.reg_verification.enabled')) {
            $this->redirect(route('user.auth.signup-success', ['hashId' => $emailConfirmation->hash_id]));
        }
        else {
            $this->redirect(route('user.auth.confirm-signup', ['token' => $emailToken]));
        }
    }

    private function checkIfEmailBlacklisted()
    {
        $blacklistService = app(BlacklistService::class);

        return $blacklistService->isEmailBlacklisted($this->emailAddress);
    }
}
