<?php

namespace App\Http\Resources\Ad;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use App\Http\Resources\User\User\UserPreviewResource;
use App\Services\Ad\AdDestinationResolver;

class AdResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $legacyCtaType = blank($this->cta_type) && filled($this->target_url) && filled($this->cta_text)
            ? 'VISIT_WEBSITE'
            : ($this->cta_type ?: 'NO_BUTTON');
        $placement = in_array($request->query('placement'), ['feed', 'reels', 'sidebar'], true)
            ? $request->query('placement')
            : 'sidebar';

        return [
            'id' => $this->id,
            'title' => $this->display_title,
            'content' => $this->display_content,
            'target_url' => app(AdDestinationResolver::class)->resolve($this->resource),
            'click_url' => url("/api/ads/click/{$this->id}?placement={$placement}"),
            'cta_text' => $this->cta_text,
            'target_topics' => $this->target_topics ?: [],
            'source_type' => $this->source_type ?: 'creative',
            'source_post_id' => $this->source_post_id,
            'advertiser' => UserPreviewResource::make($this->user),
            'objective' => $this->objective,
            'placement_flags' => $this->placement_flags ?: ['sidebar'],
            'cta_type' => $legacyCtaType,
            'destination_type' => $this->destination_type ?: 'external_url',
            'media_type' => $this->display_media_type,
            'media_url' => $this->display_media_url,
            'video_url' => $this->display_media_type === 'video' ? $this->display_media_url : null,
            'thumbnail_url' => $this->display_thumbnail_url,
            'preview_image_url' => $this->preview_image_url
        ];
    }
}
