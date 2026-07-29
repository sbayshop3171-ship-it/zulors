<?php

namespace App\Notifications\Channels;

use Illuminate\Notifications\Notification;

class WebPushChannel
{
	public function send(object $notifiable, Notification $notification)
	{
		$data = $notification->toPush($notifiable);
	}
}
