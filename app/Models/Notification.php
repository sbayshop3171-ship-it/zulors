<?php

namespace App\Models;

use App\Database\Configs\Table;
use App\Constants\Notifications;
use App\Support\Casts\ModelTimestampCast;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Notification extends DatabaseNotification
{
    use HasFactory;

    protected $casts = [
        'data' => 'array',
        'created_at' => ModelTimestampCast::class
    ];

    public $table = Table::NOTIFICATIONS;

    public function getMessageAttribute()
    {
        return __(join('.', ['notifications', $this->data['message_group'], $this->data['message_key']]), $this->data['message_params']);
    }

    public function scopeAll($query)
    {
        return $query->latest('created_at')->take(300);
    }

    public function scopeImportant($query)
    {
        return $query->all()->whereIn('type', Notifications::importantTypes());
    }

    public function scopeMentionable($query)
    {
        return $query->all()->whereIn('type', Notifications::mentionableTypes());
    }
}
