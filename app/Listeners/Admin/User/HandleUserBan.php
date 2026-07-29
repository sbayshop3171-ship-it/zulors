<?php

namespace App\Listeners\Admin\User;

use App\Events\Admin\User\UserBannedEvent;
use Illuminate\Support\Facades\Notification;
use App\Notifications\Admin\User\UserBannedNotification;

class HandleUserBan
{
    public function handle(UserBannedEvent $event): void
    {
        if(config('admin.notifications.user_banned')) {
            Notification::route('mail', config('admin.email'))->notify(new UserBannedNotification($event->userData));
        }
    }
}
