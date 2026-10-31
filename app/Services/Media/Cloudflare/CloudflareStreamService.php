<?php

namespace App\Services\Media\Cloudflare;

class CloudflareStreamService
{
    // Keep URL generation for existing media; new uploads are R2-only.
    public function playbackUrls(?string $uid): array
    {
        if(empty($uid)) {
            return [];
        }

        $customerSubdomain = trim((string) config('media.cloudflare.stream.customer_subdomain'));
        $baseUrl = $customerSubdomain ? "https://{$customerSubdomain}/{$uid}" : "https://videodelivery.net/{$uid}";

        return [
            'hls' => "{$baseUrl}/manifest/video.m3u8",
            'dash' => "{$baseUrl}/manifest/video.mpd",
            'thumbnail' => "{$baseUrl}/thumbnails/thumbnail.jpg",
            'watch' => $baseUrl,
        ];
    }
}
