<?php

namespace App\Notifications\System\Deposit;

use Illuminate\Bus\Queueable;
use App\Constants\Notifications;
use Illuminate\Notifications\Notification;
use Illuminate\Contracts\Queue\ShouldQueue;
use App\Notifications\Traits\HasSystemActor;
use App\Notifications\Traits\BaseNotification;
use Illuminate\Notifications\Messages\MailMessage;

class DepositSuccessNotification extends Notification implements ShouldQueue
{
    use Queueable,
        HasSystemActor,
        BaseNotification;

    public array $actorData;

    public $notificationType = Notifications::WALLET_DEPOSIT;

    public function __construct()
    {
        $this->actorData = $this->getSystemActor();
    }

    public function via(): array
    {
        return $this->getImportantNotificationChannels();
    }

    public function toPush(object $notifiable): array
    {
        return [];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage())->subject(__('notifications.subjects.deposit_success', locale: $notifiable->language))->view($this->notificationViewPath, [
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
            'message_group' => 'wallet',
            'message_key' => 'deposit_success',
            'message_params' => [],
            'is_viewable' => true,
            'entity' => [
            ],
            'actor' => $this->actorData,
            'metadata' => [
                'is_viewable' => true
            ]
        ];
    }

    private function getDestinationLink(): string
    {
        return url('/wallet');
    }
}
