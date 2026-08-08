<?php

namespace App\Http\Resources\User\Chat;

use App\Http\Resources\User\User\UserPreviewResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CallSessionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'call_uuid' => $this->call_uuid,
            'chat_id' => $this->chat?->chat_id,
            'media_type' => $this->media_type->value,
            'status' => $this->status->value,
            'end_reason' => $this->end_reason,
            'initiator_id' => $this->initiator_id,
            'receiver_id' => $this->receiver_id,
            'expires_at' => $this->expires_at?->toIso8601String(),
            'timestamps' => [
                'started_at' => $this->started_at?->toIso8601String(),
                'answered_at' => $this->answered_at?->toIso8601String(),
                'connected_at' => $this->connected_at?->toIso8601String(),
                'ended_at' => $this->ended_at?->toIso8601String(),
            ],
            'relations' => [
                'initiator' => $this->whenLoaded('initiator', fn () => UserPreviewResource::make($this->initiator)),
                'receiver' => $this->whenLoaded('receiver', fn () => UserPreviewResource::make($this->receiver)),
            ],
        ];
    }
}
