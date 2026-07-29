<?php

namespace App\Models;

use App\Enums\F2AType;
use App\Enums\NotificationType;
use Illuminate\Database\Eloquent\Model;

class UserSecuritySettings extends Model
{
    public $timestamps = false;

    protected $guarded = ['user_id'];

    protected $casts = [
        'login_notification' => 'boolean',
        '2fa' => 'boolean',
        '2fa_type' => F2AType::class,
        'login_notification_type' => NotificationType::class,
    ];

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id', 'id');
    }
}
