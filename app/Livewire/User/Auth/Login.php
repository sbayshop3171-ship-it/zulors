<?php

namespace App\Livewire\User\Auth;

use App\Models\User;
use Livewire\Component;
use App\Support\SocialLoginDrivers;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use App\Events\User\Auth\UserLoggedInEvent;

class Login extends Component
{
    public $loginStep = 1;
    public $authCreds;
    public $userData;
    public $activeSocialDrivers;
    public $showAllSocialOptions = false;

    public function mount(SocialLoginDrivers $socialLoginDrivers)
    {
        $this->authCreds = [
            'login' => '',
            'password' => '',
            'remember' => true
        ];

        $this->activeSocialDrivers = $socialLoginDrivers->getActiveDrivers();
    }

    public function render()
    {
        return view('livewire.user.auth.login');
    }

    public function showAllSocialLoginOptions()
    {
        $this->showAllSocialOptions = true;
    }

    public function submitForm()
    {
        if($this->loginStep == 1) {
            $this->validate(rules: [
                'authCreds.login' => ['required', 'string', 'max:62']
            ], attributes: [
                'authCreds.login' => __('auth.login_or_email')
            ]);

            $this->userData = User::where('email', $this->authCreds['login'])->orWhere('username', $this->authCreds['login'])->first();

            // Check if login is enabled
            if(! config('features.login.enabled')) {
                $this->addError('authCreds.login', __('auth.login_disabled'));

                return false;
            }

            if($this->userData) {
                $this->loginStep = 2;
            }
            else{
                $this->addError('authCreds.login', __('auth.failed'));
            }
        }
        else if($this->loginStep == 2) {
            $this->validate(rules: [
                'authCreds.login' => ['required', 'string', 'max:62'],
                'authCreds.password' => ['required', 'string', 'max:62']
            ], attributes: [
                'authCreds.login' => __('auth.login_or_email'),
                'authCreds.password' => __('auth.password_label'),
            ]);

            if(Hash::check($this->authCreds['password'], $this->userData->password)) {
                $rememberMe = (empty($this->authCreds['remember']) == true) ? false : true;

                Auth::login($this->userData, $rememberMe);

                event(new UserLoggedInEvent(me()));

                return redirect()->route('user.desktop.index');
            }
            else{
                $this->addError('authCreds.login', __('auth.failed'));
            }
        }
    }
}
