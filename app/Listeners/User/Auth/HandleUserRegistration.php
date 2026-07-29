<?php

namespace App\Listeners\User\Auth;

use Illuminate\Support\Facades\Mail;
use App\Mail\User\Auth\VerifyEmailMail;
use App\Events\User\Auth\UserRegisteredEvent;

class HandleUserRegistration
{
    /**
     * Handle the event.
     */
    public function handle(UserRegisteredEvent $event): void
    {
        Mail::to($event->data['email'])->queue(new VerifyEmailMail([
            'link' => $event->data['link'],
            'title' => __('auth.hi_there')
        ]));
    }
}
