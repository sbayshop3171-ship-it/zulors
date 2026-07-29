<?php

namespace App\Listeners\User\Notification;

use App\Services\Relations\MuteService;
use Illuminate\Notifications\Events\NotificationSending;

class HandleNotificationSuppress
{
    public function handle(NotificationSending $event): bool
    {
        $notification = $event->notification;
        $notifiable   = $event->notifiable;

        if (property_exists($notification, 'actorData')) {
            $actorData = $notification->actorData;

            if(empty($actorData['id'])) {
                return true;
            }

            $muteService = new MuteService($notifiable->id, $actorData['id']);

            if ($muteService->isMuted()) {
                return false;
            }
        }

        return true;
    }
}
