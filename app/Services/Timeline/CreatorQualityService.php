<?php

namespace App\Services\Timeline;

use App\Database\Configs\Table;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class CreatorQualityService
{
    private const CACHE_TTL_SECONDS = 86400;

    public function scoresForAuthors(array $authorIds): array
    {
        $authorIds = collect($authorIds)
            ->map(fn($authorId) => (int) $authorId)
            ->filter()
            ->unique()
            ->values();

        if($authorIds->isEmpty()) {
            return [];
        }

        $cachedScores = [];
        $missingAuthorIds = [];

        foreach($authorIds as $authorId) {
            $cachedScore = Cache::get($this->cacheKey($authorId));

            if(is_numeric($cachedScore)) {
                $cachedScores[$authorId] = (float) $cachedScore;
            }
            else {
                $missingAuthorIds[] = $authorId;
            }
        }

        if(empty($missingAuthorIds)) {
            return $cachedScores;
        }

        $computedScores = $this->computeScores($missingAuthorIds);

        foreach($computedScores as $authorId => $score) {
            Cache::put($this->cacheKey($authorId), $score, now()->addSeconds(self::CACHE_TTL_SECONDS));
        }

        return $cachedScores + $computedScores;
    }

    public function warmRecent(int $limit = 250): int
    {
        $authorIds = DB::table(Table::POSTS)
            ->where('created_at', '>=', now()->subDays(30))
            ->orderByDesc('created_at')
            ->limit(max(10, $limit))
            ->pluck('user_id')
            ->map(fn($authorId) => (int) $authorId)
            ->filter()
            ->unique()
            ->values()
            ->all();

        return count($this->computeScores($authorIds, true));
    }

    private function computeScores(array $authorIds, bool $persist = false): array
    {
        $authors = User::query()
            ->whereIn('id', $authorIds)
            ->get(['id', 'verified', 'followers_count', 'publications_count']);

        $recentPostStats = DB::table(Table::POSTS)
            ->leftJoin(Table::POST_VIDEO_METRICS, Table::POST_VIDEO_METRICS . '.post_id', '=', Table::POSTS . '.id')
            ->leftJoin(Table::REPORTS, function($join) {
                $join->on(Table::REPORTS . '.reportable_id', '=', Table::POSTS . '.id')
                    ->where(Table::REPORTS . '.reportable_type', '=', \App\Models\Post::class);
            })
            ->select(
                Table::POSTS . '.user_id',
                DB::raw('AVG(COALESCE(post_video_metrics.intelligence_score, 0)) as avg_video_intelligence'),
                DB::raw('AVG((comments_count * 3) + (bookmarks_count * 4) + (shares_count * 5) + (views_count * 0.05)) as avg_engagement'),
                DB::raw('COUNT(DISTINCT reports.id) as reports_count')
            )
            ->whereIn(Table::POSTS . '.user_id', $authorIds)
            ->where(Table::POSTS . '.created_at', '>=', now()->subDays(45))
            ->groupBy(Table::POSTS . '.user_id')
            ->get()
            ->keyBy('user_id');

        $scores = $authors->mapWithKeys(function(User $author) use ($recentPostStats, $persist) {
            $stats = $recentPostStats->get($author->id);
            $videoIntelligence = (float) ($stats->avg_video_intelligence ?? 0.0);
            $avgEngagement = (float) ($stats->avg_engagement ?? 0.0);
            $reportsCount = (int) ($stats->reports_count ?? 0);

            $score = (
                ($author->verified ? 8.0 : 0.0)
                + (log(((int) $author->followers_count) + 1) * 2.2)
                + (log(((int) $author->publications_count) + 1) * 1.2)
                + ($videoIntelligence * 0.28)
                + min(16.0, log($avgEngagement + 1) * 3.1)
                - min(18.0, $reportsCount * 3.0)
            );

            $score = round(max(-35.0, min(72.0, $score)), 4);

            if($persist) {
                Cache::put($this->cacheKey($author->id), $score, now()->addSeconds(self::CACHE_TTL_SECONDS));
            }

            return [$author->id => $score];
        })->all();

        foreach($authorIds as $authorId) {
            if(! array_key_exists($authorId, $scores)) {
                $scores[$authorId] = 0.0;

                if($persist) {
                    Cache::put($this->cacheKey($authorId), 0.0, now()->addSeconds(self::CACHE_TTL_SECONDS));
                }
            }
        }

        return $scores;
    }

    private function cacheKey(int $authorId): string
    {
        return "timeline.creator_quality.v1.{$authorId}";
    }
}
