<?php

namespace App\Http\Controllers\Api\Webhooks;

use App\Models\Post;
use App\Models\Media;
use Illuminate\Http\Request;
use App\Enums\Post\PostStatus;
use App\Enums\Media\MediaStatus;
use Illuminate\Support\Facades\Log;
use App\Http\Controllers\Controller;
use App\Traits\Http\Api\SupportsApiResponses;
use App\Events\User\Timeline\MediaProcessedEvent;
use App\Events\User\Timeline\PublicTimelinePostCreatedEvent;
use App\Services\Media\Cloudflare\CloudflareStreamService;

class CloudflareStreamWebhookController extends Controller
{
    use SupportsApiResponses;

    public function __invoke(Request $request, CloudflareStreamService $cloudflareStreamService)
    {
        if(! $this->hasValidSignature($request)) {
            return $this->responseUnauthorizedError();
        }

        $payload = $request->all();
        $uid = data_get($payload, 'uid')
            ?? data_get($payload, 'data.uid')
            ?? data_get($payload, 'video.uid')
            ?? data_get($payload, 'result.uid');

        if(empty($uid)) {
            return $this->responseSuccess();
        }

        $state = strtolower((string) (
            data_get($payload, 'status.state')
            ?? data_get($payload, 'data.status.state')
            ?? data_get($payload, 'state')
            ?? data_get($payload, 'data.state')
            ?? ''
        ));

        $readyToStream = (bool) (
            data_get($payload, 'readyToStream')
            ?? data_get($payload, 'data.readyToStream')
            ?? false
        );

        $isReady = $readyToStream || in_array($state, ['ready', 'complete', 'completed', 'published', 'live']);
        $isFailed = in_array($state, ['failed', 'error']);

        Media::query()
            ->where('disk', 'cloudflare_stream')
            ->where('source_path', $uid)
            ->with('mediaable')
            ->get()
            ->each(function (Media $media) use ($payload, $isReady, $isFailed, $cloudflareStreamService) {
                $metadata = $media->metadata ?? [];
                $metadata['webhook_state'] = $payload;
                $metadata['playback'] = $cloudflareStreamService->playbackUrls($media->source_path);

                if($isReady) {
                    $metadata['upload_state'] = 'ready';
                    $media->status = MediaStatus::PROCESSED;
                }
                else if($isFailed) {
                    $metadata['upload_state'] = 'failed';
                    $media->status = MediaStatus::FAILED;
                }
                else {
                    $metadata['upload_state'] = 'processing';
                }

                $media->metadata = $metadata;
                $media->save();

                if($media->mediaable instanceof Post) {
                    if($isReady) {
                        $media->mediaable->status = PostStatus::ACTIVE;
                        $media->mediaable->save();
                    }

                    try {
                        event(new MediaProcessedEvent($media->refresh(), $media->mediaable->user_id));

                        if($isReady) {
                            event(new PublicTimelinePostCreatedEvent($media->mediaable->refresh()));
                        }
                    }
                    catch (\Throwable $e) {
                        Log::error('Failed to broadcast Cloudflare Stream media update: ' . $e->getMessage());
                    }
                }
            });

        return $this->responseSuccess();
    }

    private function hasValidSignature(Request $request): bool
    {
        $secret = config('media.cloudflare.stream.webhook_secret');

        if(blank($secret)) {
            return true;
        }

        $signature = $request->header('X-Webhook-Signature')
            ?? $request->header('X-Cloudflare-Webhook-Signature');

        if(blank($signature)) {
            return false;
        }

        return hash_equals((string) $secret, (string) $signature);
    }
}
