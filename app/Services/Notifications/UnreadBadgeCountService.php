<?php

namespace App\Services\Notifications;

use App\Database\Configs\Table;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class UnreadBadgeCountService
{
    public function forUser(User|int $user): int
    {
        $userId = $user instanceof User ? $user->id : $user;

        $chatUnreadCount = DB::table(Table::MESSAGES . ' as messages')
            ->join(Table::CHAT_PARTICIPANTS . ' as participants', 'participants.chat_id', '=', 'messages.chat_id')
            ->where('participants.user_id', $userId)
            ->whereColumn('messages.user_id', '!=', 'participants.user_id')
            ->whereColumn('messages.id', '>', 'participants.last_read_message_id')
            ->where('messages.is_deleted', false)
            ->count();

        $activityUnreadCount = DB::table(Table::NOTIFICATIONS)
            ->where('notifiable_type', User::class)
            ->where('notifiable_id', $userId)
            ->whereNull('read_at')
            ->count();

        return (int) ($chatUnreadCount + $activityUnreadCount);
    }
}
