<?php

namespace App\Services\Media\Cloudflare;

use RuntimeException;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;

class CloudflareStreamService
{
    public function isConfigured(): bool
    {
        return (bool) config('media.cloudflare.stream.enabled')
            && filled(config('media.cloudflare.stream.account_id'))
            && filled(config('media.cloudflare.stream.api_token'));
    }

    public function createDirectUpload(array $metadata = []): array
    {
        if(! $this->isConfigured()) {
            throw new RuntimeException('Cloudflare Stream is not configured.');
        }

        $accountId = config('media.cloudflare.stream.account_id');
        $expiresAt = Carbon::now()->addMinutes(
            (int) config('media.cloudflare.stream.direct_upload_expiry_minutes', 60)
        )->toIso8601String();

        $response = Http::withToken(config('media.cloudflare.stream.api_token'))
            ->acceptJson()
            ->post("https://api.cloudflare.com/client/v4/accounts/{$accountId}/stream/direct_upload", [
                'expiry' => $expiresAt,
                'maxDurationSeconds' => (int) config('media.cloudflare.stream.max_duration_seconds', 36000),
                'requireSignedURLs' => (bool) config('media.cloudflare.stream.require_signed_urls', false),
                'meta' => $metadata,
            ]);

        if($response->failed() || ! $response->json('success', false)) {
            throw new RuntimeException(data_get($response->json(), 'errors.0.message', 'Unable to create Cloudflare Stream upload.'));
        }

        $result = $response->json('result', []);
        $uid = Arr::get($result, 'uid');

        return [
            'uid' => $uid,
            'upload_url' => Arr::get($result, 'uploadURL'),
            'expires_at' => Arr::get($result, 'expiry', $expiresAt),
            'playback' => $this->playbackUrls($uid),
        ];
    }

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
