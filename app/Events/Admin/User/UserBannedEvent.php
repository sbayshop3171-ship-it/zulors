<?php

namespace App\Events\Admin\User;

use App\Models\User;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class UserBannedEvent
{
    use Dispatchable, SerializesModels;

    public User $userData;

    public function __construct(User $userData)
    {
        $this->userData = $userData;
    }
}
