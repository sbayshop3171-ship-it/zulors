<?php

namespace App\Models;

use App\Enums\NotificationType;
use Illuminate\Database\Eloquent\Model;

class UserNotificationSettings extends Model
{
    public $timestamps = false;

    protected $casts = [
        'type' => NotificationType::class,
        'direct_messages' => 'boolean',
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
