<?php

namespace App\Console\Commands;

use App\Console\Traits\ValidatesInput;
use App\Models\User;
use App\Rules\X\XRule;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;

class ChangeUserPassword extends Command
{
    use ValidatesInput;

    protected $signature = 'user:password';

    protected $description = 'This command changes the password of a user.';

    public function handle()
    {
        $username = $this->askValid('Enter the username', [
            'required',
            'string',
            'exists:users,username'
        ]);

        $password = $this->askValid('Enter the new password', [
            'required',
            'string',
            XRule::join('min', config('user.validation.password.min')),
            XRule::join('max', config('user.validation.password.max'))
        ]);

        $user = User::where('username', $username)->first();

        if(! $user) {
            $this->error('User not found.');
            return false;
        }

        $user->update([
            'password' => Hash::make($password)
        ]);

        $this->info('Password changed successfully for user: ' . $username);

        return true;
    }
}
