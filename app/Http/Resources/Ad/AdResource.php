<?php

namespace App\Http\Resources\Ad;

use Illuminate\Http\Request;
use App\Http\Resources\User\Media\MediaResource;
use Illuminate\Http\Resources\Json\JsonResource;

class AdResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'content' => $this->content,
            'target_url' => $this->target_url,
            'click_url' => url("/api/ads/click/{$this->id}"),
            'cta_text' => $this->cta_text,
            'target_topics' => $this->target_topics ?: [],
            'preview_image_url' => $this->preview_image_url
        ];
    }
}
