<?php

namespace App\Console\Commands\Chat;

use App\Models\ChatInvite;
use Illuminate\Console\Command;

class ClearInvitations extends Command
{
    protected $signature = 'chat:invite-clear';

    protected $description = 'This command clears expired invitations from the database.';

    public function handle()
    {
        ChatInvite::where('expires_at', '<', now())->delete();
    }
}
