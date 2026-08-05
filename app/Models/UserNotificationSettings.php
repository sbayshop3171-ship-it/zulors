<?php

namespace App\Models;

use App\Enums\NotificationType;
use Illuminate\Database\Eloquent\Model;

class UserNotificationSettings extends Model
{
    public $timestamps = false;

    public static function defaultEmailPreferences(): array
    {
        return [
            'direct_messages' => false,
            'show_message_preview' => true,
            'reactions' => false,
            'comments' => false,
            'shared_posts' => false,
            'followers' => false,
            'follow_request' => false,
            'mentions' => false,
        ];
    }

    public static function defaultPushPreferences(): array
    {
        return [
            'direct_messages' => true,
            'show_message_preview' => true,
            'reactions' => true,
            'comments' => true,
            'shared_posts' => true,
            'followers' => true,
            'follow_request' => true,
            'mentions' => true,
        ];
    }

    protected $casts = [
        'type' => NotificationType::class,
        'direct_messages' => 'boolean',
        'show_message_preview' => 'boolean',
        'reactions' => 'boolean',
        'comments' => 'boolean',
        'shared_posts' => 'boolean',
        'followers' => 'boolean',
        'follow_request' => 'boolean',
        'mentions' => 'boolean',
    ];

    protected $guarded = [];

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id', 'id');
    }
}
