<?php

namespace App\Notifications\Admin\User;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use App\Models\User;

class UserBannedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    private User $userData;

    public function __construct(User $userData)
    {
        $this->userData = $userData;
    }

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(): MailMessage
    {
        return (new MailMessage())->subject(__('admin/notifications.subjects.user_banned', locale: config('app.admin_locale')))->view('emails.admin.notifications.user-banned', [
            'userData' => $this->userData
        ]);
    }
}
