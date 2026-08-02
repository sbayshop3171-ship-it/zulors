<?php

namespace App\Services\Ad;

use App\Models\Ad;
use App\Models\AdImpression;
use App\Models\User;
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

        $ads = Ad::query()
            ->published()
            ->approved()
            ->with(['media', 'impressions' => function($query) use ($fingerprint) {
                $query->where('fingerprint', $fingerprint);
            }])
            ->when($prevAdId, fn($query) => $query->where('id', '!=', $prevAdId))
            ->whereColumn('spent_budget', '<', 'total_budget')
            ->get()
            ->filter(function(Ad $ad) use ($frequencyCap) {
                $impression = $ad->impressions->first();

                return empty($impression) || $impression->impressions_count < $frequencyCap;
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

        $impression = AdImpression::query()->firstOrCreate([
            'ad_id' => $ad->id,
            'fingerprint' => $fingerprint,
        ], [
            'user_id' => $user?->id,
            'impressions_count' => 0,
        ]);

        $impression->user_id = $user?->id;
        $impression->impressions_count = $impression->impressions_count + 1;
        $impression->last_seen_at = now();
        $impression->save();

        return $impression;
    }

    public function recordClick(Ad $ad, Request $request): AdImpression
    {
        $user = $request->user() ?: auth()->user();
        $fingerprint = $this->fingerprint($request, $user);

        $impression = AdImpression::query()->firstOrCreate([
            'ad_id' => $ad->id,
            'fingerprint' => $fingerprint,
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

    public function fingerprint(Request $request, ?User $user): string
    {
        if($user) {
            return "user:{$user->id}";
        }

        return 'guest:' . sha1(($request->cookie('device_id') ?: $request->ip() ?: 'unknown') . '|' . substr((string) $request->userAgent(), 0, 120));
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
