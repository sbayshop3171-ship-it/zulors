<?php

namespace App\Http\Resources\Ad;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AdResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->display_title,
            'content' => $this->display_content,
            'target_url' => $this->target_url,
            'click_url' => url("/api/ads/click/{$this->id}"),
            'cta_text' => $this->cta_text,
            'target_topics' => $this->target_topics ?: [],
            'source_type' => $this->source_type ?: 'creative',
            'source_post_id' => $this->source_post_id,
            'media_type' => $this->display_media_type,
            'media_url' => $this->display_media_url,
            'video_url' => $this->display_media_type === 'video' ? $this->display_media_url : null,
            'thumbnail_url' => $this->display_thumbnail_url,
            'preview_image_url' => $this->preview_image_url
        ];
    }
}
