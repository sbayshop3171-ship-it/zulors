<?php

namespace App\Services\Ad;

use App\Actions\Ad\AdShowAction;
use App\Enums\Ad\AdApproval;
use App\Enums\Ad\AdStatus;
use App\Models\Ad;
use App\Models\AdDelivery;
use App\Models\AdEvent;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class AdDeliveryService
{
    public function __construct(private TargetedAdService $targetedAds)
    {
    }

    public function create(Ad $ad, Request $request, string $lane = 'relevance', array $reasons = []): AdDelivery
    {
        return AdDelivery::query()->create([
            'delivery_id' => (string) Str::uuid(),
            'ad_id' => $ad->id,
            'user_id' => $request->user()?->id,
            'viewer_key' => $this->targetedAds->fingerprint($request, $request->user()),
            'placement' => $this->placement($request),
            'selection_lane' => $lane,
            'model_version' => (string) ($ad->selection_model_version ?: 'rules-v1'),
            'reason_codes' => $reasons,
            'served_at' => now(),
        ]);
    }

    public function markViewable(AdDelivery $delivery, Request $request): AdDelivery
    {
        $ratio = (float) $request->input('visible_ratio', 0);
        $seconds = (float) $request->input('visible_seconds', 0);
        $isVideo = (bool) $request->boolean('is_video');
        abort_unless($ratio >= 0.5 && $seconds >= ($isVideo ? 2 : 1), 422, 'The ad has not met the viewability threshold.');

        return DB::transaction(function() use ($delivery, $request) {
            $delivery = AdDelivery::query()->whereKey($delivery->id)->lockForUpdate()->firstOrFail();
            $viewerKey = $this->targetedAds->fingerprint($request, $request->user());
            abort_unless(hash_equals($delivery->viewer_key, $viewerKey), 404);
            abort_unless($delivery->user_id === $request->user()?->id, 404);
            $request->query->set('placement', $delivery->placement);

            if($delivery->viewable_at) {
                return $delivery;
            }

            $ad = Ad::query()->whereKey($delivery->ad_id)->lockForUpdate()->with([
                'media', 'reelsMedia', 'sourcePost.media', 'user',
            ])->firstOrFail();

            abort_unless($this->isEligibleNow($ad, $delivery->placement), 404);
            $delivery->viewable_at = now();
            $delivery->save();

            $this->targetedAds->recordImpression($ad, $request);
            $charged = (new AdShowAction($ad))->execute();
            AdEvent::query()->firstOrCreate([
                'delivery_id' => $delivery->delivery_id,
                'event_type' => 'impression',
            ], [
                'ad_id' => $ad->id,
                'campaign_id' => $ad->id,
                'user_id' => $request->user()?->id,
                'placement' => $delivery->placement,
                'device' => $request->userAgent() && preg_match('/mobile|android|iphone|ipad/i', $request->userAgent()) ? 'mobile' : 'desktop',
                'selection_lane' => $delivery->selection_lane,
                'model_version' => $delivery->model_version,
            ]);
            if($charged) {
                AdEvent::query()->create([
                    'ad_id' => $ad->id,
                    'campaign_id' => $ad->id,
                    'user_id' => $request->user()?->id,
                    'delivery_id' => $delivery->delivery_id,
                    'event_type' => 'billable_impression',
                    'placement' => $delivery->placement,
                    'device' => $request->userAgent() && preg_match('/mobile|android|iphone|ipad/i', $request->userAgent()) ? 'mobile' : 'desktop',
                    'selection_lane' => $delivery->selection_lane,
                    'model_version' => $delivery->model_version,
                ]);
            }

            return $delivery->refresh();
        }, 3);
    }

    public function attach(Ad $ad, Request $request): AdDelivery
    {
        return $this->create(
            $ad,
            $request,
            (string) ($ad->selection_lane ?: 'relevance'),
            (array) ($ad->selection_reasons ?: []),
        );
    }

    public function linkEvent(Ad $ad, Request $request, string $eventType): ?AdDelivery
    {
        $deliveryId = $request->string('delivery_id')->toString();

        if($deliveryId === '') {
            return null;
        }

        $delivery = AdDelivery::query()->where('delivery_id', $deliveryId)->where('ad_id', $ad->id)->firstOrFail();
        abort_unless($delivery->viewer_key === $this->targetedAds->fingerprint($request, $request->user()), 404);

        $now = now();
        if($eventType === 'cta_click' && ! $delivery->cta_clicked_at) {
            $delivery->cta_clicked_at = $now;
        } elseif(in_array($eventType, ['hide', 'report'], true) && ! $delivery->negative_feedback_at) {
            $delivery->negative_feedback_at = $now;
        } elseif($eventType === 'conversion' && ! $delivery->converted_at) {
            $delivery->converted_at = $now;
        } elseif($eventType === 'video_watch') {
            $delivery->watch_time_seconds = (float) $delivery->watch_time_seconds + max(0, min(60, (float) $request->input('watch_time_seconds', 0)));
            $delivery->completion_rate = max((float) $delivery->completion_rate, min(1, max(0, (float) $request->input('completion_rate', 0))));
        }

        $delivery->save();

        return $delivery;
    }

    public function isEligibleNow(Ad $ad, string $placement): bool
    {
        if($ad->status !== AdStatus::PUBLISHED || $ad->approval !== AdApproval::APPROVED) {
            return false;
        }
        if($ad->start_at?->isFuture() || $ad->end_at?->isPast()) {
            return false;
        }
        if((float) $ad->spent_budget >= (float) $ad->total_budget) {
            return false;
        }

        return in_array($placement, ['feed', 'reels', 'sidebar'], true)
            && (empty($ad->placement_flags) ? $placement === 'sidebar' : in_array($placement, $ad->placement_flags, true))
            && $ad->isSourceAvailableForPlacement($placement);
    }

    private function placement(Request $request): string
    {
        return in_array($request->query('placement'), ['feed', 'reels', 'sidebar'], true)
            ? $request->query('placement')
            : 'sidebar';
    }
}
