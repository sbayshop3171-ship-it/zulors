<?php

namespace App\Livewire\User\Auth;

use App\Models\User;
use App\Rules\X\XRule;
use Livewire\Component;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use App\Models\EmailConfirmation;
use Illuminate\Validation\Rules\Password;

class Reset extends Component
{
    public $confirmationData;
    public string $newPassword;
    public $userData;

    public function mount()
    {
        $this->userData = User::where('email', $this->confirmationData->email)->first();
    }

    public function render()
    {
        return view('livewire.user.auth.reset');
    }

    public function submitForm()
    {
        $rules = ['required', 'string', XRule::join('min', config('user.validation.password.min')), XRule::join('max', config('user.validation.password.max'))];

        if(config('user.password_strength_control')) {
            array_push($rules, Password::min(config('user.validation.password.min'))->letters()->mixedCase()->numbers()->symbols());
        }

        $this->validate(rules: [
            'newPassword' => $rules
        ], attributes: [
            'newPassword' => __('auth.password_label')
        ]);

        $this->userData->update([
            'password' => Hash::make($this->newPassword)
        ]);

        EmailConfirmation::where('email', $this->confirmationData->email)->delete();

        Auth::login($this->userData);

        $this->redirect(route('user.desktop.index'));
    }
}
