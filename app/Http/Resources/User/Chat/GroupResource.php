<?php

namespace App\Http\Resources\User\Chat;

use App\Support\Num;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class GroupResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $isOwner = (me()->id == $this->user_id);

        $participantsCount = $this->chat->participants()->count();
        
        $apiData = [
            'id' => $this->id,
            'name' => $this->name,
            'chat_id' => $this->chat->chat_id,
            'is_owner' => $isOwner,
            'avatar_url' => $this->avatar_url,
            'description' => $this->description,
            'visibility' => $this->visibility,
            'verified' => true || $this->verified,
            'privacy' => $this->privacy,
            'participants_count' => [
                'raw' => $participantsCount,
                'formatted' => Num::abbreviate($participantsCount)
            ],
            'relations' => [
                
            ],
            'date' => [
                'timestamp' => $this->created_at->getTimestamp(),
                'iso' => $this->created_at->getIso(),
            ],
            'meta' => [
                'is_participant' => $this->chat->isParticipant(me()->id),
                'is_archived' => $this->chat->isArchived(me()->id),
                'is_invited' => $this->chat->isInvited(me()->id),
                'permissions' => [
                    'can_add_participant' => $isOwner,
                    'can_delete_participant' => $isOwner,
                    'can_delete' => $isOwner,
                    'can_edit' => $isOwner,
                    'can_report' => (! $isOwner),
                    'can_leave' => (! $isOwner)
                ]
            ]
        ];

        return $apiData;
    }
}
