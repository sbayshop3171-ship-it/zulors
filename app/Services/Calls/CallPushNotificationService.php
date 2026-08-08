<?php

namespace App\Services\Calls;

use App\Models\CallSession;
use App\Notifications\User\Call\CancelCallNotification;
use Throwable;

class CallPushNotificationService
{
    public function cancelIncomingNotification(CallSession $callSession): void
    {
        if(! config('notifications.push.enabled')) {
            return;
        }

        $callSession->loadMissing(['chat', 'participants.user']);

        $callSession->participants
            ->pluck('user')
            ->filter()
            ->unique('id')
            ->each(function ($user) use ($callSession) {
                try {
                    $user->notify(new CancelCallNotification($callSession));
                }
                catch(Throwable $exception) {
                    // Realtime call state should not fail because push cleanup is unavailable.
                }
            });
    }
}
