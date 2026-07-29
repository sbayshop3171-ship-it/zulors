<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UserPrivacySettings extends Model
{
    public $timestamps = false;

    protected $casts = [
        'email_privacy' => 'boolean',
        'phone_privacy' => 'boolean',
        'birthdate_privacy' => 'boolean',
        'country_privacy' => 'boolean',
        'city_privacy' => 'boolean',
        'gender_privacy' => 'boolean',
        'online_privacy' => 'boolean',
        'last_seen_privacy' => 'boolean',
        'search_privacy' => 'boolean'
    ];

    protected $guarded = [
        'user_id'
    ];

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id', 'id');
    }
}
