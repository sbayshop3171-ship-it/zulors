<?php

namespace App\Services\Timeline;

use App\Database\Configs\Table;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class ReelQualityService
{
    private const CACHE_TTL_SECONDS = 3600;

    public function scoresForPosts(array $postIds): array
    {
        $postIds = collect($postIds)
            ->map(fn($postId) => (int) $postId)
            ->filter()
            ->unique()
            ->values();

        if($postIds->isEmpty()) {
            return [];
        }

        $cachedScores = [];
        $missingPostIds = [];

        foreach($postIds as $postId) {
            $cachedScore = Cache::get($this->cacheKey($postId));

            if(is_numeric($cachedScore)) {
                $cachedScores[$postId] = (float) $cachedScore;
            }
            else {
                $missingPostIds[] = $postId;
            }
        }

        if(empty($missingPostIds)) {
            return $cachedScores;
        }

        $computedScores = $this->computeScores($missingPostIds);

        foreach($computedScores as $postId => $score) {
            Cache::put($this->cacheKey($postId), $score, now()->addSeconds(self::CACHE_TTL_SECONDS));
        }

        return $cachedScores + $computedScores;
    }

    public function warmRecent(int $limit = 500): int
    {
        $postIds = DB::table(Table::POSTS)
            ->where('type', \App\Enums\Post\PostType::VIDEO->value)
            ->where('created_at', '>=', now()->subDays(14))
            ->orderByDesc('created_at')
            ->limit(max(10, $limit))
            ->pluck('id')
            ->map(fn($postId) => (int) $postId)
            ->filter()
            ->values()
            ->all();

        return count($this->computeScores($postIds, true));
    }

    private function computeScores(array $postIds, bool $persist = false): array
    {
        $videoMetrics = DB::table(Table::POST_VIDEO_METRICS)
            ->select('post_id', 'intelligence_score', 'avg_completion_rate', 'completion_rate', 'skip_rate', 'rewatch_rate')
            ->whereIn('post_id', $postIds)
            ->get()
            ->keyBy('post_id');

        $eventStats = DB::table(Table::FEED_EVENTS)
            ->select('post_id', 'metadata')
            ->whereIn('post_id', $postIds)
            ->whereIn('event_type', [
                VideoIntelligenceService::EVENT_PLAY,
                VideoIntelligenceService::EVENT_WATCH,
                VideoIntelligenceService::EVENT_SKIP,
                VideoIntelligenceService::EVENT_COMPLETE,
                VideoIntelligenceService::EVENT_LOOP,
            ])
            ->where('created_at', '>=', now()->subDays(7))
            ->get()
            ->groupBy('post_id')
            ->map(function($events) {
                $firstFrameValues = [];
                $stallValues = [];

                foreach($events as $event) {
                    $metadata = is_array($event->metadata) ? $event->metadata : json_decode((string) $event->metadata, true);

                    if(is_numeric(data_get($metadata, 'first_frame_ms'))) {
                        $firstFrameValues[] = (float) data_get($metadata, 'first_frame_ms');
                    }

                    if(is_numeric(data_get($metadata, 'stall_count'))) {
                        $stallValues[] = (float) data_get($metadata, 'stall_count');
                    }
                }

                return (object) [
                    'avg_first_frame_ms' => empty($firstFrameValues) ? 0.0 : array_sum($firstFrameValues) / count($firstFrameValues),
                    'avg_stall_count' => empty($stallValues) ? 0.0 : array_sum($stallValues) / count($stallValues),
                ];
            });

        $scores = [];

        foreach($postIds as $postId) {
            $metric = $videoMetrics->get($postId);
            $stats = $eventStats->get($postId);
            $firstFrameMs = (float) ($stats->avg_first_frame_ms ?? 0.0);
            $stallCount = (float) ($stats->avg_stall_count ?? 0.0);

            $score = (
                (float) ($metric->intelligence_score ?? 0.0)
                + ((float) ($metric->avg_completion_rate ?? 0.0) * 24.0)
                + ((float) ($metric->completion_rate ?? 0.0) * 18.0)
                + ((float) ($metric->rewatch_rate ?? 0.0) * 14.0)
                - ((float) ($metric->skip_rate ?? 0.0) * 35.0)
                - min(12.0, $stallCount * 4.0)
                - min(10.0, max(0.0, ($firstFrameMs - 450.0) / 120.0))
            );

            $scores[$postId] = round(max(-80.0, min(95.0, $score)), 4);

            if($persist) {
                Cache::put($this->cacheKey($postId), $scores[$postId], now()->addSeconds(self::CACHE_TTL_SECONDS));
            }
        }

        return $scores;
    }

    private function cacheKey(int $postId): string
    {
        return "timeline.reel_quality.v1.{$postId}";
    }
}
