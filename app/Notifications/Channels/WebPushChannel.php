<?php

namespace App\Notifications\Channels;

use App\Services\Notifications\FirebaseCloudMessagingService;
use App\Services\Notifications\PushNotificationPayloadFactory;
use Illuminate\Notifications\Notification;

class WebPushChannel
{
	public function send(object $notifiable, Notification $notification)
	{
		if(! config('notifications.push.enabled')) {
			return;
		}

		$message = app(PushNotificationPayloadFactory::class)->make($notifiable, $notification);

		app(FirebaseCloudMessagingService::class)->sendToUser($notifiable, $message);
	}
}
