<?php

namespace App\Models;

use App\Enums\Chat\ChatType;
use App\Database\Configs\Table;
use App\Support\Casts\ModelTimestampCast;
use Illuminate\Database\Eloquent\Model;

class Chat extends Model
{
    public $guarded = [];

    const UPDATED_AT = null;

    public $casts = [
        'type' => ChatType::class,
        'last_activity' => ModelTimestampCast::class,
        'created_at' => ModelTimestampCast::class
    ];

    public function scopeChatsHistory($query)
    {
        return $query->participatedChats()->whereNotNull('last_activity')->excludeArchived();
    }

    public function scopeChatsArchive($query)
    {
        return $query->participatedChats()->whereNotNull('last_activity')->archivedChats();
    }

    public function scopeArchivedChats($query)
    {
        return $query->whereIn('id', function ($subQuery) {
            $subQuery->select('chat_id')->from(Table::ARCHIVED_CHATS)->where('user_id', me()->id);
        });
    }

    public function scopeParticipatedChats($query)
    {
        return $query->whereHas('participants', function ($q) {
            $q->whereIn('user_id', [me()->id]);
        })->excludeDeleted();
    }

    public function scopeWithUnreadMessagesCountForUser($query, int $userId)
    {
        return $query->withCount([
            'messages as unread_messages_count' => function ($q) use ($userId) {
                $q->excludeDeleted()
                    ->whereNot('user_id', $userId)
                    ->whereRaw(sprintf(
                        '%s.id > COALESCE((SELECT %s.last_read_message_id FROM %s WHERE %s.chat_id = %s.chat_id AND %s.user_id = ? LIMIT 1), 0)',
                        Table::MESSAGES,
                        Table::CHAT_PARTICIPANTS,
                        Table::CHAT_PARTICIPANTS,
                        Table::CHAT_PARTICIPANTS,
                        Table::MESSAGES,
                        Table::CHAT_PARTICIPANTS
                    ), [$userId]);
            }
        ]);
    }

    public function scopeExcludeArchived($query)
    {
        return $query->whereNotIn('id', function ($subQuery) {
            $subQuery->select('chat_id')->from(Table::ARCHIVED_CHATS)->where('user_id', me()->id);
        });
    }

    public function scopeExcludeDeleted($query)
    {
        return $query->whereNotIn('id', function ($subQuery) {
            $subQuery->select('chat_id')->from(Table::HIDDEN_CHATS)->where('user_id', me()->id);
        });
    }

    public function participants()
    {
        return $this->hasMany(ChatParticipant::class, 'chat_id', 'id');
    }

    public function group()
    {
        return $this->hasOne(Group::class, 'chat_id', 'id');
    }

    public function invites()
    {
        return $this->hasMany(ChatInvite::class, 'chat_id', 'id');
    }

    public function archived()
    {
        return $this->hasMany(ArchivedChat::class, 'chat_id', 'id');
    }

    public function interlocutor()
    {
        return $this->hasOne(ChatParticipant::class, 'chat_id', 'id')
            ->whereNot('user_id', me()->id)->with(['user']);
    }

    public function lastMessage()
    {
        return $this->hasOne(Message::class, 'chat_id', 'id')
            ->latestOfMany('id')->excludeDeleted();
    }

    public function getUnreadMessagesCount()
    {
        if(array_key_exists('unread_messages_count', $this->getAttributes())) {
            return (int) $this->getAttribute('unread_messages_count');
        }

        $userParticipant = $this->participants()->where('user_id', me()->id)->first();

        if(empty($userParticipant)) {
            return 0;
        }

        if(empty($userParticipant->last_read_message_id)) {
            return $this->messages()->whereNot('user_id', me()->id)->count();
        }

        return $this->messages()
            ->whereNot('user_id', me()->id)
            ->where('id', '>', $userParticipant->last_read_message_id)
            ->count();
    }

    public function messages()
    {
        return $this->hasMany(Message::class, 'chat_id', 'id');
    }

    public function isParticipant(int $userId)
    {
        return $this->participants()->where('user_id', $userId)->exists();
    }

    public function isInvited(int $userId)
    {
        return $this->invites()->pending()->where('receiver_id', $userId)->exists();
    }

    public function addParticipant(int $userId)
    {
        $chatColors = config('chat.colors');

        $this->participants()->create([
            'user_id' => $userId, 
            'joined_at' => now(),
            'metadata' => [
                'color' => $chatColors[array_rand($chatColors)]
            ]
        ]);
    }

    public function removeParticipant(int $userId)
    {
        $this->participants()->where('user_id', $userId)->delete();
    }

    public function isArchived(int $userId)
    {
        return $this->archived()->where('user_id', $userId)->exists();
    }

    public function unarchiveChat(int $userId)
    {
        $this->archived()->where('user_id', $userId)->delete();
    }

    public function archiveChat(int $userId)
    {
        $this->archived()->create([
            'user_id' => $userId,
            'type' => $this->type
        ]);
    }
}
