<?php

namespace App\Listeners\User\Auth;

use App\Events\User\Auth\UserRegisteredEvent;
use App\Jobs\User\Auth\SendSignupOtpEmail;

class HandleUserRegistration
{
    /**
     * Handle the event.
     */
    public function handle(UserRegisteredEvent $event): void
    {
        SendSignupOtpEmail::dispatch(
            $event->data['confirmation_id'],
            $event->data['token']
        );
    }
}
