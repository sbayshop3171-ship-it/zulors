<?php

namespace App\Http\Controllers\Api\User\Timeline\Telemetry;

use App\Models\Media;
use App\Models\Post;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Services\Timeline\FeedTelemetryService;
use App\Services\Timeline\VideoIntelligenceService;
use App\Traits\Http\Api\SupportsApiResponses;

class TelemetryController extends Controller
{
    use SupportsApiResponses;

    public function store(Request $request, VideoIntelligenceService $videoIntelligenceService, FeedTelemetryService $feedTelemetryService)
    {
        $request->validate([
            'events' => ['required', 'array', 'max:25'],
            'events.*.event_type' => ['required', 'string', 'max:48'],
            'events.*.post_id' => ['required', 'integer'],
            'events.*.media_id' => ['nullable', 'integer'],
            'events.*.dwell_time_seconds' => ['nullable', 'numeric', 'min:0', 'max:86400'],
            'events.*.watch_time_seconds' => ['nullable', 'numeric', 'min:0', 'max:86400'],
            'events.*.duration_seconds' => ['nullable', 'numeric', 'min:0', 'max:86400'],
            'events.*.completion_rate' => ['nullable', 'numeric', 'min:0', 'max:5'],
            'events.*.current_time_seconds' => ['nullable', 'numeric', 'min:0', 'max:86400'],
            'events.*.loop_count' => ['nullable', 'integer', 'min:0', 'max:100'],
            'events.*.session_id' => ['nullable', 'string', 'max:80'],
            'events.*.feed_type' => ['nullable', 'string', 'max:32'],
            'events.*.source' => ['nullable', 'string', 'max:32'],
            'events.*.refresh_reason' => ['nullable', 'string', 'max:32'],
            'events.*.position' => ['nullable', 'integer', 'min:0', 'max:10000'],
            'events.*.viewport_ratio' => ['nullable', 'numeric', 'min:0', 'max:1'],
            'events.*.visible_ms' => ['nullable', 'integer', 'min:0', 'max:86400000'],
        ]);

        $acceptedCount = 0;

        foreach($request->array('events') as $event) {
            $post = Post::activeById((int) data_get($event, 'post_id'))->with('media')->first();

            if(empty($post)) {
                continue;
            }

            if($feedTelemetryService->isPostEvent((string) data_get($event, 'event_type'))) {
                $feedTelemetryService->record(me(), $post, $event);
                $acceptedCount++;

                continue;
            }

            $media = $this->resolveVideoMedia($post, data_get($event, 'media_id'));

            if(empty($media)) {
                continue;
            }

            $videoIntelligenceService->record(me(), $post, $media, $event);
            $acceptedCount++;
        }

        return $this->responseSuccess([
            'data' => [
                'accepted' => $acceptedCount
            ]
        ]);
    }

    private function resolveVideoMedia(Post $post, mixed $mediaId): ?Media
    {
        return $post->media->first(function(Media $media) use ($mediaId) {
            if(! $media->type->isVideo()) {
                return false;
            }

            if(empty($mediaId)) {
                return true;
            }

            return $media->id === (int) $mediaId;
        });
    }
}
