<?php

namespace App\Services\Ad;

use App\Models\Ad;
use App\Models\AdImpression;
use App\Models\User;
use App\Models\AdEvent;
use App\Services\Timeline\TopicExtractionService;
use Illuminate\Http\Request;

class TargetedAdService
{
    public function __construct(private TopicExtractionService $topicExtractionService)
    {
    }

    public function selectForRequest(Request $request): ?Ad
    {
        $user = $request->user() ?: auth()->user();
        $fingerprint = $this->fingerprint($request, $user);
        $prevAdId = $request->integer('prev_ad_id');
        $userTopics = $this->userTopics($user);
        $frequencyCap = (int) config('ads.targeting.frequency_cap', 3);
        $placement = $this->placement($request);

        $ads = Ad::query()
            ->published()
            ->approved()
            ->with(['user', 'media', 'sourcePost.media', 'impressions' => function($query) use ($fingerprint, $placement) {
                $query->where('fingerprint', $fingerprint)
                    ->where('placement', $placement);
            }])
            ->when($prevAdId, fn($query) => $query->where('id', '!=', $prevAdId))
            ->whereColumn('spent_budget', '<', 'total_budget')
            ->get()
            ->filter(function(Ad $ad) use ($frequencyCap, $placement) {
                if(! $this->supportsPlacement($ad, $placement)) {
                    return false;
                }

                if($ad->start_at && $ad->start_at->isFuture()) {
                    return false;
                }

                if($ad->end_at && $ad->end_at->isPast()) {
                    return false;
                }

                if(! $ad->isSourceAvailable()) {
                    return false;
                }

                $impression = $ad->impressions->first();

                $cap = (int) ($ad->frequency_cap ?: $frequencyCap);

                return empty($impression) || $impression->impressions_count < $cap;
            });

        if($ads->isEmpty()) {
            return null;
        }

        return $ads->sortByDesc(function(Ad $ad) use ($userTopics) {
            return $this->scoreAd($ad, $userTopics);
        })->values()->first();
    }

    public function recordImpression(Ad $ad, Request $request): AdImpression
    {
        $user = $request->user() ?: auth()->user();
        $fingerprint = $this->fingerprint($request, $user);
        $placement = $this->placement($request);

        $impression = AdImpression::query()->firstOrCreate([
            'ad_id' => $ad->id,
            'fingerprint' => $fingerprint,
            'placement' => $placement,
        ], [
            'user_id' => $user?->id,
            'impressions_count' => 0,
            'device' => $this->device($request),
        ]);

        $impression->user_id = $user?->id;
        $impression->device = $this->device($request);
        $impression->impressions_count = $impression->impressions_count + 1;
        $impression->last_seen_at = now();
        $impression->save();

        return $impression;
    }

    public function recordClick(Ad $ad, Request $request): AdImpression
    {
        $user = $request->user() ?: auth()->user();
        $fingerprint = $this->fingerprint($request, $user);
        $placement = $this->placement($request);

        $impression = AdImpression::query()->firstOrCreate([
            'ad_id' => $ad->id,
            'fingerprint' => $fingerprint,
            'placement' => $placement,
        ], [
            'user_id' => $user?->id,
            'impressions_count' => 0,
            'clicks_count' => 0,
        ]);

        $impression->user_id = $user?->id;
        $impression->clicks_count = $impression->clicks_count + 1;
        $impression->last_clicked_at = now();
        $impression->save();

        $ad->increment('clicks_count');

        return $impression;
    }

    public function recordEvent(Ad $ad, Request $request): AdEvent
    {
        $eventType = $request->string('event_type')->toString();

        abort_unless(in_array($eventType, ['impression', 'unique_impression', 'click', 'cta_click', 'three_second_view', 'video_watch', 'completed_view'], true), 422);

        $user = $request->user() ?: auth()->user();

        return AdEvent::query()->create([
            'ad_id' => $ad->id,
            'campaign_id' => $ad->id,
            'user_id' => $user?->id,
            'event_type' => $eventType,
            'placement' => $this->placement($request),
            'device' => $this->device($request),
            'watch_time_seconds' => $request->input('watch_time_seconds'),
            'completion_rate' => $request->input('completion_rate'),
            'session_id' => $request->string('session_id')->limit(120)->value() ?: null,
        ]);
    }

    public function fingerprint(Request $request, ?User $user): string
    {
        if($user) {
            return "user:{$user->id}";
        }

        return 'guest:' . sha1(($request->cookie('device_id') ?: $request->ip() ?: 'unknown') . '|' . substr((string) $request->userAgent(), 0, 120));
    }

    private function placement(Request $request): string
    {
        return in_array($request->query('placement'), ['feed', 'reels', 'sidebar'], true)
            ? $request->query('placement')
            : 'sidebar';
    }

    private function device(Request $request): string
    {
        return $request->userAgent() && preg_match('/mobile|android|iphone|ipad/i', $request->userAgent())
            ? 'mobile'
            : 'desktop';
    }

    private function supportsPlacement(Ad $ad, string $placement): bool
    {
        $placements = $ad->placement_flags;

        return empty($placements)
            ? $placement === 'sidebar'
            : in_array($placement, $placements, true);
    }

    private function userTopics(?User $user): array
    {
        if(empty($user)) {
            return [];
        }

        return $user->interestScores()
            ->where('score', '>', 0)
            ->orderByDesc('score')
            ->limit(12)
            ->pluck('topic')
            ->map(fn($topic) => $this->topicExtractionService->normalizeTopic($topic))
            ->filter()
            ->values()
            ->all();
    }

    private function scoreAd(Ad $ad, array $userTopics): float
    {
        $targetTopics = collect($ad->target_topics ?: [])
            ->map(fn($topic) => $this->topicExtractionService->normalizeTopic($topic))
            ->filter()
            ->values()
            ->all();

        $topicMatches = count(array_intersect($targetTopics, $userTopics));
        $topicMatchRate = empty($targetTopics) ? 0 : ($topicMatches / max(1, count($targetTopics)));
        $untargetedFallback = empty($targetTopics) ? 25 : 0;
        $impression = $ad->impressions->first();
        $viewerFrequency = (int) ($impression?->impressions_count ?? 0);
        $clickThroughRate = ((int) $ad->views_count > 0)
            ? ((int) $ad->clicks_count / max(1, (int) $ad->views_count))
            : 0.0;
        $qualityScore = min(250, ($clickThroughRate * 1000) + (log(((int) $ad->clicks_count) + 1) * 12));
        $bidScore = (float) $ad->price_per_view * 1000;
        $budgetScore = min(120, max(0, ((float) $ad->total_budget - (float) $ad->spent_budget)));
        $frequencyPenalty = $viewerFrequency * 120;

        return ($topicMatches * 420)
            + ($topicMatchRate * 280)
            + $untargetedFallback
            + $bidScore
            + $budgetScore
            + $qualityScore
            - $frequencyPenalty;
    }
}
