<?php

namespace App\Http\Resources\User\Chat;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use App\Http\Resources\User\User\UserPreviewResource;

class RequestResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $apiData = [
            'id' => $this->id,
            'status' => $this->status->value,
            'chat_type' => $this->chat->type->value,
            'relations' => [
                'sender' => UserPreviewResource::make($this->sender),
                'chat' => [
                    'chat_id' => $this->chat->chat_id 
                ],
                'group' => [
                    'name' => $this->chat->group->name
                ]
            ],
            'date' => [
                'time_ago' => $this->created_at->getTimeAgo()
            ]
        ];

        return $apiData;
    }
}
