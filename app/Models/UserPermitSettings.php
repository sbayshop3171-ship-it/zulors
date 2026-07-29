<?php

namespace App\Models;

use App\Enums\User\PrivacyPermit;
use Illuminate\Database\Eloquent\Model;

class UserPermitSettings extends Model
{
    public $timestamps = false;

    protected $guarded = ['user_id'];

    protected $casts = [
        'followers' => PrivacyPermit::class,
        'direct_messages' => PrivacyPermit::class,
        'story_replies' => PrivacyPermit::class,
        'group_invites' => PrivacyPermit::class,
        'mentions' => PrivacyPermit::class,
        'payment_transfers' => PrivacyPermit::class,
    ];

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id', 'id');
    }
}
