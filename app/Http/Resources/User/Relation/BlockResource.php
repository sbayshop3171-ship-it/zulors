<?php

namespace App\Http\Resources\User\Relation;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BlockResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->blocked->id,
            'name' => $this->blocked->name,
            'avatar_url' => $this->blocked->avatar_url,
            'username' => $this->blocked->username,
            'caption' => $this->blocked->getCaption(),
            'verified' => $this->blocked->verified,
            'bio' => $this->blocked->bio,
            'date' => [
                'raw' => $this->created_at->getTimestamp(),
                'formatted' => $this->created_at->getCalendar()
            ]
        ];
    }
}
