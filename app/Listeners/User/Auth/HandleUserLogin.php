<?php

namespace App\Listeners\User\Auth;

use App\Services\World\IpLocationService;
use App\Events\User\Auth\UserLoggedInEvent;
use App\Actions\User\UpdateUserDeviceAction;

class HandleUserLogin
{
    /**
     * Handle the event.
     */
    public function handle(UserLoggedInEvent $event): void
    {
        $user = $event->user;

        (new UpdateUserDeviceAction())->execute($user);
    }
}
