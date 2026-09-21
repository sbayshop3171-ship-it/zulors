<?php

namespace App\Services\Ad;

use App\Models\Ad;

class AdDestinationResolver
{
    public function resolve(Ad $ad): ?string
    {
        if($ad->cta_type === 'NO_BUTTON') {
            return null;
        }

        return match($ad->destination_type) {
            'internal_post' => $ad->sourcePost?->url ?: $ad->target_url,
            'profile' => $ad->user?->url ?: $ad->target_url,
            default => $ad->target_url,
        };
    }
}