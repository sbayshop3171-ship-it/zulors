<?php

namespace App\Notifications\Traits;

use App\Support\Num;
use Illuminate\Support\Str;

trait BaseNotification
{
	protected $notificationViewPath = 'emails.user.notifications.email-notification';
	
    public function broadcastType(): string
    {
        return 'main.notification';
    }

    public function databaseType(): string
    {
        return $this->notificationType;
    }

	protected function getUnreadCount(object $notifiable): array
	{
		$unreadCount = $notifiable->getUnreadNotificationsCount();
		$unreadCount += 1;

		return [
			'formatted' => Num::abbreviate($unreadCount),
			'raw' => $unreadCount
		];
	}

	public function toBroadcast(object $notifiable): array
	{
        $unreadCount = $this->getUnreadCount($notifiable);

		return [
			'data' => $unreadCount
		];
	}
	
	protected function cutContent(string $content): string
	{
		return Str::limit($content, 50);
	}

	protected function getImportantNotificationChannels(): array
	{
		$channels = ['database'];

		if($this->isEmailEnabled()) {
			array_push($channels, 'mail');
		}

		if($this->isBroadcastEnabled()) {
			array_push($channels, 'broadcast');
		}

		return $channels;
	}

	protected function isPushEnabled(): bool
	{
		return config('notifications.push.enabled');
	}

	protected function isBroadcastEnabled(): bool
	{
		return config('notifications.broadcast.enabled');
	}

	protected function isEmailEnabled(): bool
	{
		return config('notifications.email.enabled');
	}
}
