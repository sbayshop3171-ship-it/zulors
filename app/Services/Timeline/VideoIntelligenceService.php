<?php

namespace App\Services\Timeline;

use App\Models\FeedEvent;
use App\Models\Media;
use App\Models\Post;
use App\Models\PostVideoMetric;
use App\Models\User;

class VideoIntelligenceService
{
    public const EVENT_PLAY = 'video_play';
    public const EVENT_WATCH = 'video_watch';
    public const EVENT_SKIP = 'video_skip';
    public const EVENT_COMPLETE = 'video_complete';
    public const EVENT_LOOP = 'video_loop';

    public function __construct(private UserInterestService $userInterestService)
    {
    }

    public function record(User|int|null $user, Post $post, ?Media $media, array $payload): PostVideoMetric
    {
        $durationSeconds = $this->positiveFloat(data_get($payload, 'duration_seconds', 0));
        $watchTimeSeconds = $this->positiveFloat(data_get($payload, 'watch_time_seconds', 0));
        $completionRate = $this->completionRate($watchTimeSeconds, $durationSeconds, $payload);
        $eventType = $this->normalizeEventType((string) data_get($payload, 'event_type', self::EVENT_WATCH), $completionRate, $watchTimeSeconds);
        $userId = $user instanceof User ? $user->id : $user;

        FeedEvent::query()->create([
            'user_id' => $userId,
            'post_id' => $post->id,
            'media_id' => $media?->id,
            'event_type' => $eventType,
            'watch_time_seconds' => $watchTimeSeconds,
            'duration_seconds' => $durationSeconds,
            'completion_rate' => round($completionRate, 4),
            'session_id' => data_get($payload, 'session_id'),
            'metadata' => [
                'current_time_seconds' => $this->positiveFloat(data_get($payload, 'current_time_seconds', 0)),
                'loop_count' => (int) data_get($payload, 'loop_count', 0),
                'source' => data_get($payload, 'source', 'video_player'),
                'feed_type' => data_get($payload, 'feed_type'),
                'position' => data_get($payload, 'position'),
                'playback_session_id' => data_get($payload, 'playback_session_id'),
                'is_muted' => data_get($payload, 'is_muted'),
            ],
        ]);

        $metric = PostVideoMetric::query()->firstOrCreate([
            'post_id' => $post->id,
        ], [
            'media_id' => $media?->id,
        ]);

        $this->updateMetric($metric, $eventType, $watchTimeSeconds, $completionRate, (int) data_get($payload, 'loop_count', 0));
        $this->updateInterest($user, $post, $eventType, $completionRate);

        return $metric->refresh();
    }

    private function updateMetric(PostVideoMetric $metric, string $eventType, float $watchTimeSeconds, float $completionRate, int $loopCount): void
    {
        $playsIncrement = $eventType === self::EVENT_PLAY ? 1 : 0;
        $completionsIncrement = $eventType === self::EVENT_COMPLETE || $completionRate >= 0.9 ? 1 : 0;
        $loopsIncrement = max(0, $loopCount) + ($eventType === self::EVENT_LOOP || $completionRate > 1.05 ? 1 : 0);
        $skipsIncrement = $eventType === self::EVENT_SKIP ? 1 : 0;
        $rewatchesIncrement = $completionRate > 1.05 ? 1 : 0;

        $totalMeasuredEvents = max(1, $metric->plays_count + $metric->completions_count + $metric->skips_count);
        $nextMeasuredEvents = $totalMeasuredEvents + 1;
        $avgCompletionRate = (($metric->avg_completion_rate * $totalMeasuredEvents) + $completionRate) / $nextMeasuredEvents;

        $metric->plays_count = $metric->plays_count + $playsIncrement + ($eventType !== self::EVENT_PLAY ? 1 : 0);
        $metric->completions_count = $metric->completions_count + $completionsIncrement;
        $metric->skips_count = $metric->skips_count + $skipsIncrement;
        $metric->loops_count = $metric->loops_count + $loopsIncrement;
        $metric->rewatches_count = $metric->rewatches_count + $rewatchesIncrement;
        $metric->watch_time_seconds = round($metric->watch_time_seconds + $watchTimeSeconds, 3);
        $metric->avg_completion_rate = round($avgCompletionRate, 4);
        $metric->completion_rate = round($metric->plays_count > 0 ? $metric->completions_count / $metric->plays_count : 0, 4);
        $metric->skip_rate = round($metric->plays_count > 0 ? $metric->skips_count / $metric->plays_count : 0, 4);
        $metric->rewatch_rate = round($metric->plays_count > 0 ? $metric->rewatches_count / $metric->plays_count : 0, 4);
        $metric->intelligence_score = round($this->intelligenceScore($metric), 4);
        $metric->last_event_at = now();
        $metric->save();
    }

    private function updateInterest(User|int|null $user, Post $post, string $eventType, float $completionRate): void
    {
        if(empty($user)) {
            return;
        }

        $delta = match(true) {
            $eventType === self::EVENT_SKIP => -8.0,
            $eventType === self::EVENT_LOOP || $completionRate > 1.05 => 12.0,
            $eventType === self::EVENT_COMPLETE || $completionRate >= 0.9 => 8.0,
            $completionRate >= 0.5 => 4.0,
            default => 1.0,
        };

        $this->userInterestService->recordPostInteraction($user, $post, UserInterestService::EVENT_VIEW, $delta);
    }

    private function normalizeEventType(string $eventType, float $completionRate, float $watchTimeSeconds): string
    {
        if(in_array($eventType, [self::EVENT_PLAY, self::EVENT_SKIP, self::EVENT_COMPLETE, self::EVENT_LOOP], true)) {
            return $eventType;
        }

        if($completionRate > 1.05) {
            return self::EVENT_LOOP;
        }

        if($completionRate >= 0.9) {
            return self::EVENT_COMPLETE;
        }

        if($watchTimeSeconds > 0 && $completionRate < 0.35) {
            return self::EVENT_SKIP;
        }

        return self::EVENT_WATCH;
    }

    private function completionRate(float $watchTimeSeconds, float $durationSeconds, array $payload): float
    {
        $explicitRate = data_get($payload, 'completion_rate');

        if(is_numeric($explicitRate)) {
            return max(0.0, min(5.0, (float) $explicitRate));
        }

        if($durationSeconds <= 0) {
            return 0.0;
        }

        return max(0.0, min(5.0, $watchTimeSeconds / $durationSeconds));
    }

    private function intelligenceScore(PostVideoMetric $metric): float
    {
        return max(-45, min(65,
            ($metric->avg_completion_rate * 30)
            + ($metric->completion_rate * 24)
            + ($metric->rewatch_rate * 22)
            + (min(10, $metric->loops_count) * 3)
            - ($metric->skip_rate * 42)
        ));
    }

    private function positiveFloat($value): float
    {
        return is_numeric($value) ? max(0.0, (float) $value) : 0.0;
    }
}
