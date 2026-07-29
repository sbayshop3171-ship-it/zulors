<?php

namespace App\Livewire\Admin\Auth;

use App\Enums\User\UserRole;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Livewire\Component;

class Login extends Component
{
    public $authCreds;

    public function mount()
    {
        $this->authCreds = [
            'login' => '',
            'password' => '',
        ];
    }

    public function submitForm()
    {
        $this->validate([
            'authCreds.login' => ['required', 'string', 'max:62'],
            'authCreds.password' => ['required', 'string', 'max:62'],
        ], attributes: [
            'authCreds.login' => __('auth.login_or_email'),
            'authCreds.password' => __('auth.enter_password'),
        ]);

        $userData = User::query()
            ->where(function ($query) {
                $query->where('email', $this->authCreds['login'])
                    ->orWhere('username', $this->authCreds['login']);
            })
            ->whereIn('role', [
                UserRole::ADMIN->value,
                UserRole::ROOT->value,
            ])
            ->first();

        if(! $userData || ! Hash::check($this->authCreds['password'], $userData->password)) {
            $this->addError('authCreds.login', __('auth.failed'));

            return false;
        }

        Auth::login($userData);

        return redirect()->route('admin.dash.index');
    }

    public function render()
    {
        return view('livewire.admin.auth.login');
    }
}
