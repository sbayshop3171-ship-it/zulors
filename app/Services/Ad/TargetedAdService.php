<?php

namespace App\Services\Ad;

use App\Models\Ad;
use App\Models\AdDelivery;
use App\Models\AdEvent;
use App\Models\AdImpression;
use App\Models\User;
use App\Services\Timeline\TopicExtractionService;
use Illuminate\Http\Request;

class TargetedAdService
{
    public function __construct(
        private TopicExtractionService $topicExtractionService,
        private UserAdInterestService $interests,
        private AdTopicPolicy $topicPolicy,
    ) {}

    public function selectForRequest(Request $request): ?Ad
    {
        $user = $request->user() ?: auth()->user();
        $viewerKey = $this->fingerprint($request, $user);
        $placement = $this->placement($request);
        $sponsoredAdId = $request->integer('sponsored_ad_id');
        $prevAdId = $request->integer('prev_ad_id');

        $ads = Ad::query()->published()->approved()
            ->with([
                'user', 'media', 'reelsMedia', 'sourcePost' => fn($q) => $q->timelineFormatPosts(),
                'events' => fn($q) => $q->whereIn('event_type', ['completed_view', 'hide', 'report']),
            ])
            ->when($sponsoredAdId > 0, fn($q) => $q->whereKey($sponsoredAdId))
            ->when($prevAdId > 0, fn($q) => $q->whereKeyNot($prevAdId))
            ->whereColumn('spent_budget', '<', 'total_budget')->get()
            ->filter(fn(Ad $ad) => $this->eligible($ad, $placement) && $this->underFrequencyCap($ad, $viewerKey));

        if($ads->isEmpty()) return null;

        $userScores = $user && $this->interests->isEnabled($user) ? $this->interests->scores($user) : [];
        $context = collect((array) $request->input('context_topics', []))
            ->map(fn($topic) => $this->topicPolicy->normalizeAllowed((string) $topic))->filter()->unique()->values()->all();
        $hash = hexdec(substr(hash('sha256', $viewerKey . '|' . now()->format('Y-m-d-H')), 0, 7)) % 100;
        $rankingEnabled = (bool) config('ads.personalization.ranking_enabled', false);
        $holdout = ! $rankingEnabled || $hash < (int) config('ads.personalization.holdout_percent', 10);
        $laneHash = hexdec(substr(hash('sha256', $viewerKey . '|' . now()->format('Y-m-d-H-i')), 0, 7)) % 100;
        $lane = $holdout ? 'control' : ($laneHash < 80 ? 'relevance' : ($laneHash < 95 ? 'contextual' : 'exploration'));

        $ranked = $ads->map(function(Ad $ad) use ($userScores, $context, $lane, $holdout) {
            $score = $this->scoreAd($ad, $userScores, $context, $holdout);
            $ad->setAttribute('selection_lane', $lane);
            $ad->setAttribute('selection_model_version', $holdout ? 'control-v1' : 'rules-v1');
            $ad->setAttribute('selection_reasons', $this->reasonCodes($ad, $userScores, $context));
            return [$ad, $score];
        });

        if($lane === 'exploration') return $ranked->values()->random()[0];
        if($lane === 'contextual' && $context) {
            $contextual = $ranked->filter(fn($row) => count(array_intersect($context, $this->targetTopics($row[0]))) > 0);
            if($contextual->isNotEmpty()) $ranked = $contextual;
        }

        return $ranked->sortByDesc(fn($row) => $row[1])->values()->first()[0];
    }

    public function recordImpression(Ad $ad, Request $request): AdImpression
    {
        $user = $request->user() ?: auth()->user();
        $impression = AdImpression::query()->firstOrCreate([
            'ad_id' => $ad->id,
            'fingerprint' => $this->fingerprint($request, $user),
            'placement' => $this->placement($request),
        ], ['user_id' => $user?->id, 'impressions_count' => 0, 'device' => $this->device($request)]);
        $impression->user_id = $user?->id;
        $impression->device = $this->device($request);
        $impression->impressions_count++;
        $impression->last_seen_at = now();
        $impression->save();
        return $impression;
    }

    public function recordClick(Ad $ad, Request $request): AdImpression
    {
        $user = $request->user() ?: auth()->user();
        $impression = AdImpression::query()->firstOrCreate([
            'ad_id' => $ad->id,
            'fingerprint' => $this->fingerprint($request, $user),
            'placement' => $this->placement($request),
        ], ['user_id' => $user?->id, 'impressions_count' => 0, 'clicks_count' => 0]);
        $impression->user_id = $user?->id;
        $impression->clicks_count++;
        $impression->last_clicked_at = now();
        $impression->save();
        $ad->increment('clicks_count');
        return $impression;
    }

    public function recordEvent(Ad $ad, Request $request): AdEvent
    {
        $eventType = $request->string('event_type')->toString();
        abort_unless(in_array($eventType, ['impression','unique_impression','click','cta_click','reel_open','like','comment','share','save','hide','report','video_start','three_second_view','six_second_view','video_watch','completed_view','landing_visit','lead','conversion'], true), 422);
        $user = $request->user() ?: auth()->user();
        $delivery = app(AdDeliveryService::class)->linkEvent($ad, $request, $eventType);

        return AdEvent::query()->create([
            'ad_id' => $ad->id, 'campaign_id' => $ad->id, 'user_id' => $user?->id,
            'delivery_id' => $delivery?->delivery_id, 'event_type' => $eventType,
            'placement' => $delivery?->placement ?: $this->placement($request), 'device' => $this->device($request),
            'selection_lane' => $delivery?->selection_lane,
            'model_version' => $delivery?->model_version,
            'watch_time_seconds' => $request->input('watch_time_seconds'),
            'completion_rate' => $request->input('completion_rate'),
            'session_id' => $request->string('session_id')->limit(120)->value() ?: null,
        ]);
    }

    public function fingerprint(Request $request, ?User $user): string
    {
        if($user) return 'user:' . $user->id;
        $device = trim((string) $request->cookie('device_id'));
        $session = $request->hasSession() ? (string) $request->session()->getId() : '';
        $fallback = hash('sha256', (string) $request->ip() . '|' . substr((string) $request->userAgent(), 0, 160));
        $stable = $device !== '' ? $device : ($session !== '' ? $session : ((string) $request->header('X-Client-Session') ?: $fallback));
        return 'guest:' . hash('sha256', substr($stable, 0, 200));
    }

    private function eligible(Ad $ad, string $placement): bool
    {
        return in_array($placement, ['feed','reels','sidebar'], true)
            && (empty($ad->placement_flags) ? $placement === 'sidebar' : in_array($placement, $ad->placement_flags, true))
            && (! $ad->start_at || ! $ad->start_at->isFuture())
            && (! $ad->end_at || ! $ad->end_at->isPast())
            && $ad->isSourceAvailableForPlacement($placement);
    }

    private function underFrequencyCap(Ad $ad, string $viewerKey): bool
    {
        $base = AdDelivery::query()->where('ad_id', $ad->id)->where('viewer_key', $viewerKey)->whereNotNull('viewable_at');
        $day = (clone $base)->where('viewable_at', '>=', now()->subDay())->count();
        $week = (clone $base)->where('viewable_at', '>=', now()->subDays(7))->count();
        $campaignCap = (int) ($ad->frequency_cap ?: 0);
        $dailyCap = min((int) config('ads.personalization.daily_viewable_cap', 2), $campaignCap > 0 ? $campaignCap : PHP_INT_MAX);
        $weeklyCap = min((int) config('ads.personalization.weekly_viewable_cap', 4), $campaignCap > 0 ? $campaignCap : PHP_INT_MAX);
        if($day >= $dailyCap || $week >= $weeklyCap) return false;

        $lastClick = (clone $base)->whereNotNull('cta_clicked_at')->orderByDesc('cta_clicked_at')->first();
        if($lastClick) {
            if($lastClick->cta_clicked_at->gt(now()->subHours((int) config('ads.personalization.post_click_cooldown_hours', 24)))) return false;
            $reminders = (clone $base)->where('viewable_at', '>', $lastClick->cta_clicked_at)->count();
            if($reminders >= (int) config('ads.personalization.post_click_reminder_cap', 2)) return false;
        } else {
            $recent = (clone $base)->where('viewable_at', '>=', now()->subDays(7))->orderByDesc('viewable_at')->limit(3)->get();
            if($recent->count() >= 3) {
                $lastPositive = AdDelivery::query()->where('ad_id', $ad->id)->where('viewer_key', $viewerKey)
                    ->whereNotNull('cta_clicked_at')->max('cta_clicked_at');
                $deliveryIds = $recent->pluck('delivery_id');
                $engaged = AdEvent::query()->whereIn('delivery_id', $deliveryIds)
                    ->whereIn('event_type', ['like', 'comment', 'share', 'save', 'conversion'])->exists();
                if(! $lastPositive && ! $engaged) return false;
            }
        }

        return ! AdDelivery::query()->where('ad_id', $ad->id)->where('viewer_key', $viewerKey)->whereNotNull('negative_feedback_at')->exists()
            && ! AdDelivery::query()->where('ad_id', $ad->id)->where('viewer_key', $viewerKey)->whereNotNull('converted_at')->exists();
    }

    private function userTopics(?User $user): array
    {
        if(! $user || ! $this->interests->isEnabled($user)) return [];
        return $this->interests->scores($user);
    }

    private function targetTopics(Ad $ad): array
    {
        return collect($ad->target_topics ?: [])->map(fn($topic) => $this->topicPolicy->normalizeAllowed($topic))->filter()->values()->all();
    }

    private function scoreAd(Ad $ad, array $userScores, array $context, bool $control): float
    {
        $topics = $this->targetTopics($ad);
        $interest = 0.0;
        foreach($topics as $topic) $interest += max(-100, min(1000, (float) ($userScores[$topic] ?? 0)));
        $contextMatch = count(array_intersect($topics, $context));
        $ctr = (int) $ad->views_count > 0 ? (int) $ad->clicks_count / max(1, (int) $ad->views_count) : 0;
        $quality = min(250, $ctr * 1000 + log((int) $ad->clicks_count + 1) * 12);
        $completedViews = $ad->events->where('event_type', 'completed_view')->count();
        $negativeFeedback = $ad->events->whereIn('event_type', ['hide', 'report'])->count();
        $quality += min(60, $completedViews * 2) - min(100, $negativeFeedback * 20);
        $pacing = min(120, max(0, (float) $ad->total_budget - (float) $ad->spent_budget));
        $bid = (float) $ad->price_per_view * 1000;
        return $control ? $quality + $pacing + $bid : $interest * 2.2 + $contextMatch * 400 + $quality + $pacing + $bid;
    }

    private function reasonCodes(Ad $ad, array $scores, array $context): array
    {
        $topics = $this->targetTopics($ad);
        $matches = array_values(array_intersect($topics, array_keys($scores)));
        if($matches) return ['interest_match'];
        if(array_intersect($topics, $context)) return ['context_match'];
        return ['broad_reach'];
    }

    private function placement(Request $request): string
    {
        return in_array($request->query('placement'), ['feed','reels','sidebar'], true) ? $request->query('placement') : 'sidebar';
    }

    private function device(Request $request): string
    {
        return $request->userAgent() && preg_match('/mobile|android|iphone|ipad/i', $request->userAgent()) ? 'mobile' : 'desktop';
    }
}
