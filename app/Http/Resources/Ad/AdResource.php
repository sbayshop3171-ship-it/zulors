<?php

namespace App\Http\Resources\Ad;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use App\Http\Resources\User\User\UserPreviewResource;
use App\Http\Resources\User\Timeline\TimelineResource;
use App\Services\Ad\AdDestinationResolver;

class AdResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $sourceType = $this->source_type ?: 'creative';
        $destination = app(AdDestinationResolver::class)->resolve($this->resource);
        $legacyCtaType = blank($this->cta_type) && filled($this->target_url) && filled($this->cta_text)
            ? 'VISIT_WEBSITE'
            : ($this->cta_type ?: 'NO_BUTTON');
        $placement = in_array($request->query('placement'), ['feed', 'reels', 'sidebar'], true)
            ? $request->query('placement')
            : 'sidebar';
        $ctaEnabled = $legacyCtaType !== 'NO_BUTTON' && filled($destination);
        $ctaIcon = match ($this->destination_type) {
            'whatsapp' => 'whatsapp',
            'phone' => 'phone',
            'message' => 'message-circle-02',
            'profile', 'internal_post' => 'arrow-up-right',
            default => 'arrow-up-right',
        };

        return [
            'id' => $this->id,
            'campaign_id' => $this->id,
            'creative_id' => $this->id,
            'title' => $this->display_title,
            'headline' => $this->headline ?: $this->display_title,
            'primary_text' => $this->primary_text ?: $this->display_content,
            'content' => $this->primary_text ?: $this->display_content,
            // Deliberately never expose the advertiser's destination URL to clients.
            'target_url' => null,
            'click_url' => $ctaEnabled ? url("/api/ads/click/{$this->id}?placement={$placement}") : null,
            'cta_enabled' => $ctaEnabled,
            'cta_icon' => $ctaEnabled ? $ctaIcon : null,
            'cta_text' => $ctaEnabled ? $this->cta_text : null,
            'target_topics' => $this->target_topics ?: [],
            'source_type' => $sourceType,
            'source_post_id' => $this->source_post_id,
            'source_post' => $sourceType === 'post' && $this->sourcePost
                ? TimelineResource::make($this->sourcePost)
                : null,
            'advertiser' => UserPreviewResource::make($this->user),
            'objective' => $this->objective,
            'placement_flags' => $this->placement_flags ?: ['sidebar'],
            'placement' => $placement,
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
