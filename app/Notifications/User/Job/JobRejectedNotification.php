<?php

namespace App\Notifications\User\Job;

use App\Models\JobListing;
use Illuminate\Bus\Queueable;
use App\Constants\Notifications;
use Illuminate\Notifications\Notification;
use Illuminate\Contracts\Queue\ShouldQueue;
use App\Notifications\Traits\HasSystemActor;
use App\Notifications\Traits\BaseNotification;
use Illuminate\Notifications\Messages\MailMessage;

class JobRejectedNotification extends Notification implements ShouldQueue
{
    use Queueable,
        HasSystemActor,
        BaseNotification;

    public array $actorData;

    public $notificationType = Notifications::JOB_REJECTED;

    public function __construct(private JobListing $jobData)
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
        return (new MailMessage())->subject(__('notifications.subjects.job_rejected', locale: $notifiable->language))->view($this->notificationViewPath, [
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
            'message_key' => 'job_rejected',
            'message_params' => [
                'title' => $this->jobData->title
            ],
            'entity' => [
                'id' => $this->jobData->id,
                'hash_id' => $this->jobData->hash_id,
                'content' => $this->cutContent($this->jobData->title),
                'business_url' => route('business.jobs.show', $this->jobData->id),
                'job_url' => $this->jobData->url
            ],
            'actor' => $this->actorData,
            'metadata' => [
                'is_viewable' => true
            ]
        ];
    }

    private function getDestinationLink(): string
    {
        return route('business.jobs.show', $this->jobData->id);
    }
}
