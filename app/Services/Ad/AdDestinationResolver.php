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
            'message' => $ad->target_url,
            'phone' => filled($ad->user?->phone) ? 'tel:' . preg_replace('/[^0-9+]/', '', $ad->user->phone) : $ad->target_url,
            'whatsapp' => filled($ad->user?->phone) ? 'https://wa.me/' . preg_replace('/[^0-9]/', '', $ad->user->phone) : $ad->target_url,
            'internal_post' => $ad->sourcePost?->url ?: $ad->target_url,
            'profile' => $ad->user?->profile_url ?: $ad->target_url,
            'external_url', 'product', 'offer', 'booking' => $ad->target_url,
            default => $ad->target_url,
        };
    }
}