<?php

namespace App\Http\Resources\User\Chat\Editor;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class EditGroupResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'description' => $this->description,
            'is_draft' => $this->status->isDraft(),
            'avatar_url' => $this->avatar_url,
            'visibility' => $this->visibility,
            'verified' => $this->verified,
            'relations' => [
                'chat' => [
                    'id' => $this->chat->id,
                    'chat_id' => $this->chat->chat_id,
                    'type' => $this->chat->type
                ]
            ]
        ];
    }
}
