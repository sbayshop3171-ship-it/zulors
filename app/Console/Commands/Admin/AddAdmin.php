<?php

namespace App\Console\Commands\Admin;

use App\Models\User;
use App\Enums\User\UserRole;
use Illuminate\Console\Command;
use App\Console\Traits\ValidatesInput;

class AddAdmin extends Command
{
    use ValidatesInput;
    
    protected $signature = 'admin:root';

    protected $description = 'This command assigns the root admin role to the provided user.';

    public function handle()
    {
        $adminUsername = $this->askValid('Enter the username:', [
            'required',
            'string',
            'exists:users,username'
        ]);

        $adminData = User::where('username', $adminUsername)->first();

        if(! $adminData) {
            $this->error('User not found.');

            return false;
        }

        $adminData->update([
            'role' => UserRole::ROOT
        ]);

        $this->info('Admin (Root) has been added successfully.');
        $this->warn('Please use this mention to add root admin role to the first user in the system.');

        return true;
    }
}
