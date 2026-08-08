<?php
/*
|--------------------------------------------------------------------------
| Zulors - The Zulors Web Application.
|--------------------------------------------------------------------------
| Author: Mansur Terla. Full-Stack Web Developer, UI/UX Designer.
| Website: www.terla.me
| E-mail: mansurtl.contact@gmail.com
| Instagram: @mansur_terla
| Telegram: @mansurtl_contact
|--------------------------------------------------------------------------
| Copyright (c)  Zulors. All rights reserved.
|--------------------------------------------------------------------------
*/

namespace App\Http\Resources\User\Chat;

use App\Models\User;
use App\Support\Num;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ChatResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $unreadMessagesCount = $this->resource->getUnreadMessagesCount();
        $lastActivity = $this->last_activity ?: $this->created_at;

        $chatItem = [
            'chat_id' => $this->chat_id,
            'is_group' => $this->type->isGroup(),
            'type' => $this->type->value,
            'unread_messages_count' => [
                'raw' => $unreadMessagesCount,
                'formatted' => Num::abbreviate($unreadMessagesCount)
            ],
            'last_activity' => [
                'time_ago' => $lastActivity->getTimeAgo(),
                'raw' => $lastActivity->getTimestamp(),
                'formatted' => $lastActivity->getCalendar()
            ],
            'last_message_id' => null,
            'last_message' => null,
            'last_message_type' => null,
            'last_message_is_mine' => false,
            'is_deleted' => false
        ];

        if ($this->type->isDirect()) {
            $interlocutor = $this->interlocutor;

            if(isset($interlocutor->user) && $interlocutor->user) {
                $chatItem['chat_info'] = [
                    'id' => $interlocutor->user->id,
                    'name' => $interlocutor->user->name,
                    'avatar_url' => $interlocutor->user->avatar_url,
                    'verified' => $interlocutor->user->isVerified(),
                    'presence' => $this->getUserPresencePayload($interlocutor->user),
                ];
            }
            else {
                $chatItem['chat_info'] = [
                    'id' => 0,
                    'name' => 'Deleted Account',
                    'verified' => false,
                    'avatar_url' => asset(config('user.avatar'))
                ];
            }
        }
        else if ($this->type->isGroup()) {
            $chatItem['chat_info'] = [
                'id' => $this->group->id,
                'name' => $this->group->name,
                'avatar_url' => $this->group->avatar_url,
                'verified' => $this->group->isVerified(),
            ];
        }

        if (! empty($this->lastMessage)) {
            $chatItem['last_message_id'] = $this->lastMessage->id;
            $chatItem['last_message_type'] = $this->lastMessage->type->value;
            $chatItem['last_message_is_mine'] = $this->lastMessage->isSender();

            if ($this->lastMessage->is_deleted) {
                $chatItem['is_deleted'] = true;
            }
            else {
                $chatItem['last_message'] = $this->getLastMessagePreview();
            }
        }

        return $chatItem;
    }

    private function getLastMessagePreview(): ?string
    {
        if(! empty($this->lastMessage->content)) {
            $preview = $this->lastMessage->content;
        }
        else {
            $preview = match($this->lastMessage->type->value) {
                'image' => trans('labels.image'),
                'audio' => trans('labels.audio'),
                'video_circle', 'video' => trans('labels.video'),
                'document' => trans('labels.document'),
                'location' => trans('labels.location'),
                'call' => $this->lastMessage->content,
                default => null
            };
        }

        if(! empty($preview) && $this->lastMessage->isSender()) {
            return trans('labels.you') . ': ' . $preview;
        }

        return $preview;
    }

    private function getUserPresencePayload(?User $userData): ?array
    {
        if(empty($userData) || empty($userData->last_active)) {
            return null;
        }

        $lastActiveAt = Carbon::parse($userData->last_active);
        $minutesAgo = max(0, (int) $lastActiveAt->diffInMinutes(now()));
        $isOnline = $userData->isOnline();

        return [
            'is_online' => $isOnline,
            'recent' => (! $isOnline && $minutesAgo < 60),
            'minutes_ago' => $minutesAgo,
            'short_label' => ($isOnline ? null : "{$minutesAgo}m"),
            'last_seen_at' => [
                'raw' => $userData->getLastActive()->getTimestamp(),
                'formatted' => $userData->getLastActive()->getCalendar(),
                'time_ago' => $userData->getLastActive()->getTimeAgo()
            ]
        ];
    }
}
