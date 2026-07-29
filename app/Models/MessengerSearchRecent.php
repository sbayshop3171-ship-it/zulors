<?php

namespace App\Models;

use App\Database\Configs\Table;
use Illuminate\Database\Eloquent\Model;

class MessengerSearchRecent extends Model
{
    public $table = Table::MESSENGER_SEARCH_RECENTS;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'searched_at' => 'datetime',
        ];
    }

    public function targetUser()
    {
        return $this->belongsTo(User::class, 'target_user_id', 'id');
    }
}
