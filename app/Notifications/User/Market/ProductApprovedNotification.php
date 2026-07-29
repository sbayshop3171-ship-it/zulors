<?php

namespace App\Notifications\User\Market;

use App\Models\Product;
use Illuminate\Bus\Queueable;
use App\Constants\Notifications;
use Illuminate\Notifications\Notification;
use Illuminate\Contracts\Queue\ShouldQueue;
use App\Notifications\Traits\HasSystemActor;
use App\Notifications\Traits\BaseNotification;
use Illuminate\Notifications\Messages\MailMessage;

class ProductApprovedNotification extends Notification implements ShouldQueue
{
    use Queueable,
        HasSystemActor,
        BaseNotification;

    public array $actorData;

    public $notificationType = Notifications::PRODUCT_APPROVED;

    public function __construct(private Product $productData)
    {
        $this->actorData = $this->getSystemActor();
    }

    public function via(object $notifiable): array
    {
        return $this->getImportantNotificationChannels();
    }

    public function toPush(object $notifiable): array
    {
        return [];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage())->subject(__('notifications.subjects.product_approved', locale: $notifiable->language))->view($this->notificationViewPath, [
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

    private function getData(): array
    {
        return [
            'message_group' => 'important',
            'message_key' => 'product_approved',
            'message_params' => [
                'title' => $this->productData->title
            ],
            'entity' => [
                'id' => $this->productData->id,
                'hash_id' => $this->productData->hash_id,
                'content' => $this->cutContent($this->productData->title),
                'preview_lqip_base64' => $this->productData->preview_image_url,
                'business_url' => route('business.market.show', $this->productData->id),
                'product_url' => $this->productData->url
            ],
            'actor' => $this->actorData,
            'metadata' => [
                'is_viewable' => true
            ]
        ];
    }

    private function getDestinationLink(): string
    {
        return route('business.market.show', $this->productData->id);
    }
}
