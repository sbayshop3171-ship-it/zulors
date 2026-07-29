<?php

namespace App\Notifications\User\Important;

use Illuminate\Bus\Queueable;
use App\Constants\Notifications;
use App\Notifications\Traits\HasUserActor;
use Illuminate\Notifications\Notification;
use Illuminate\Contracts\Queue\ShouldQueue;
use App\Notifications\Traits\BaseNotification;
use Illuminate\Notifications\Messages\MailMessage;

class AccountLinkedNotification extends Notification implements ShouldQueue
{
    use Queueable,
        BaseNotification,
        HasUserActor;

    public $notificationType = Notifications::ACCOUNT_LINKED;

    public array $actorData;

    public function __construct()
    {
        $this->actorData = $this->getUserActor();
    }

    public function via(object $notifiable): array
    {
        return $this->getImportantNotificationChannels();
    }

    public function toPush(object $notifiable): array
    {
        return [
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage())->subject(__('notifications.subjects.account_linked', locale: $notifiable->language))->view($this->notificationViewPath, [
            'notifiable' => $notifiable,
            'data' => $this->getData(),
            'notificationType' => $this->notificationType,
            'destinationLink' => $this->getDestinationLink(),
            'locale' => $notifiable->language
        ]);
    }

    public function toDatabase(): array
    {
        return $this->getData();
    }

    private function getData()
    {
        return [
            'message_group' => 'important',
            'message_key' => 'account_linked',
            'message_params' => [],
            'metadata' => [
                'is_viewable' => true
            ],
            'entity' => [
            ],
            'actor' => $this->actorData
        ];
    }

    protected function getDestinationLink(): string
	{
		return url("/@{$this->actorData['username']}");
	}
}
