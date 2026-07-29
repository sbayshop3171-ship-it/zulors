<?php

namespace App\Livewire\User\Linker;

use App\Models\User;
use Livewire\Component;
use Illuminate\Support\Facades\Hash;
use App\Notifications\User\Important\AccountLinkedNotification;

class LinkerForm extends Component
{
    public $loginStep = 1;
    public $authCreds;
    public $userData;

    public function mount()
    {
        $this->authCreds = [
            'login' => '',
            'password' => ''
        ];
    }

    public function render()
    {
        return view('livewire.user.linker.linker-form');
    }

    public function submitForm()
    {
        if(! me()->isMasterAccount()) {
            $this->addError('authCreds.login', __('auth.master_account_error', ['app_name' => config('app.name')]));
        }

        else if($this->loginStep == 1) {
            $this->validate(rules: [
                'authCreds.login' => ['required', 'string', 'max:62']
            ], attributes: [
                'authCreds.login' => __('auth.login_or_email')
            ]);
            
            $this->userData = User::active()
                ->excludeSelf()
                ->where(function($query) {
                    $query->where('email', $this->authCreds['login'])->orWhere('username', $this->authCreds['login']);
                })->first();
                
            if($this->userData) {
                if($this->userData->owner_account_id == me()->id) {
                    $this->addError('authCreds.login', __('auth.already_linked_account_error', ['app_name' => config('app.name')]));
                }

                else if(! $this->userData->isMasterAccount()) {
                    $this->addError('authCreds.login', __('auth.linked_account_error', ['app_name' => config('app.name')]));
                }

                else {
                    $this->loginStep = 2;
                }
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
                $this->userData->update([
                    'owner_account_id' => me()->id
                ]);

                $this->userData->notify(new AccountLinkedNotification());

                return redirect()->route('user.desktop.index');
            }
            else{
                $this->addError('authCreds.login', __('auth.failed'));
            }
        }
    }
}
