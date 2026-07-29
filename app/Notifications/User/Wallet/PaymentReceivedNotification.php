<?php

namespace App\Notifications\User\Wallet;

use Illuminate\Bus\Queueable;
use App\Constants\Notifications;
use App\Notifications\Traits\HasUserActor;
use Illuminate\Notifications\Notification;
use Illuminate\Contracts\Queue\ShouldQueue;
use App\Notifications\Traits\BaseNotification;
use Illuminate\Notifications\Messages\MailMessage;

class PaymentReceivedNotification extends Notification implements ShouldQueue
{
    use Queueable,
        BaseNotification,
        HasUserActor;

    public $notificationType = Notifications::PAYMENT_RECEIVED;

    public array $actorData;

    private string $amount;

    public function __construct(string $amount)
    {
        $this->actorData = $this->getUserActor();
        $this->amount = $amount;
    }

    public function via(object $notifiable): array
    {
        return $this->getImportantNotificationChannels();
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage())->subject(__('notifications.subjects.payment_received', locale: $notifiable->language))->view($this->notificationViewPath, [
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
            'message_key' => 'payment_received',
            'message_params' => [
                'amount' => $this->amount
            ],
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
		return url("/wallet");
	}
}
