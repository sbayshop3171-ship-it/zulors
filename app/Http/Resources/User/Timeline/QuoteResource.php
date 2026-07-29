<?php

namespace App\Http\Resources\User\Timeline;

use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use App\Http\Resources\User\Media\MediaResource;
use Illuminate\Http\Resources\Json\JsonResource;

class QuoteResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $apiData = [
            'id' => $this->id,
            'content' => e($this->content),
            'type' => $this->type,
            'hash_id' => $this->hash_id,
            'relations' => [
                'user' => [
                    'avatar_url' => $this->user->avatar_url,
                    'name' => $this->user->name,
                    'caption' => $this->user->getCaption(),
                    'username' => $this->user->username
                ],
                'media' => []
            ],
            'date' => [
                'iso' => $this->created_at->getIso(),
                'time_ago' => $this->created_at->getTimeAgo()
            ]
        ];

        if($this->type->isMedia()) {
            $apiData['relations']['media'] = $this->media->map(function($item) {
                return MediaResource::make($item);
            });
        }
        
        return $apiData;
    }
}
