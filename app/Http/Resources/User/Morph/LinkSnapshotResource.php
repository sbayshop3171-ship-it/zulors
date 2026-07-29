<?php

namespace App\Http\Resources\User\Morph;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class LinkSnapshotResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'description' => $this->description,
            'url' => $this->url,
            'metadata' => $this->metadata
        ];
    }
}
