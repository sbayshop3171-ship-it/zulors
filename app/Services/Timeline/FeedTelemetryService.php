<?php

namespace App\Services\Timeline;

use App\Models\FeedEvent;
use App\Models\Post;
use App\Models\User;

class FeedTelemetryService
{
    public const EVENT_POST_IMPRESSION = 'post_impression';
    public const EVENT_POST_DWELL = 'post_dwell';
    public const EVENT_POST_QUICK_SKIP = 'post_quick_skip';
    public const EVENT_POST_NOT_INTERESTED = 'post_not_interested';
    public const EVENT_POST_HIDE = 'post_hide';

    public const POST_EVENT_TYPES = [
        self::EVENT_POST_IMPRESSION,
        self::EVENT_POST_DWELL,
        self::EVENT_POST_QUICK_SKIP,
        self::EVENT_POST_NOT_INTERESTED,
        self::EVENT_POST_HIDE,
    ];

    public const FEEDBACK_EVENT_TYPES = [
        self::EVENT_POST_NOT_INTERESTED,
        self::EVENT_POST_HIDE,
    ];

    public const SEEN_EVENT_TYPES = [
        self::EVENT_POST_IMPRESSION,
        self::EVENT_POST_DWELL,
        self::EVENT_POST_QUICK_SKIP,
        self::EVENT_POST_NOT_INTERESTED,
        self::EVENT_POST_HIDE,
        VideoIntelligenceService::EVENT_PLAY,
        VideoIntelligenceService::EVENT_WATCH,
        VideoIntelligenceService::EVENT_SKIP,
        VideoIntelligenceService::EVENT_COMPLETE,
        VideoIntelligenceService::EVENT_LOOP,
    ];

    public function __construct(private UserInterestService $userInterestService)
    {
    }

    public function isPostEvent(string $eventType): bool
    {
        return in_array($eventType, self::POST_EVENT_TYPES, true);
    }

    public function record(User|int|null $user, Post $post, array $payload): FeedEvent
    {
        $userId = $user instanceof User ? $user->id : $user;
        $dwellSeconds = $this->dwellSeconds($payload);
        $eventType = $this->normalizeEventType((string) data_get($payload, 'event_type'), $dwellSeconds);

        $event = FeedEvent::query()->create([
            'user_id' => $userId,
            'post_id' => $post->id,
            'media_id' => data_get($payload, 'media_id'),
            'event_type' => $eventType,
            'watch_time_seconds' => $dwellSeconds,
            'duration_seconds' => 0,
            'completion_rate' => 0,
            'session_id' => data_get($payload, 'session_id'),
            'metadata' => [
                'feed_type' => data_get($payload, 'feed_type'),
                'source' => data_get($payload, 'source', 'timeline'),
                'position' => data_get($payload, 'position'),
                'refresh_reason' => data_get($payload, 'refresh_reason'),
                'viewport_ratio' => data_get($payload, 'viewport_ratio'),
                'visible_ms' => data_get($payload, 'visible_ms'),
            ],
        ]);

        $this->updateInterest($user, $post, $eventType, $dwellSeconds);

        return $event;
    }

    private function updateInterest(User|int|null $user, Post $post, string $eventType, float $dwellSeconds): void
    {
        if(empty($user) || $eventType === self::EVENT_POST_IMPRESSION) {
            return;
        }

        if($eventType === self::EVENT_POST_HIDE) {
            $this->userInterestService->recordPostInteraction($user, $post, UserInterestService::EVENT_HIDE, -20.0);

            return;
        }

        if($eventType === self::EVENT_POST_NOT_INTERESTED) {
            $this->userInterestService->recordPostInteraction($user, $post, UserInterestService::EVENT_HIDE, -12.0);

            return;
        }

        $delta = match(true) {
            $eventType === self::EVENT_POST_QUICK_SKIP || $dwellSeconds < 2 => -3.0,
            $dwellSeconds < 8 => 1.0,
            $dwellSeconds < 20 => 4.0,
            default => 8.0,
        };

        $this->userInterestService->recordPostInteraction($user, $post, UserInterestService::EVENT_VIEW, $delta);
    }

    private function normalizeEventType(string $eventType, float $dwellSeconds): string
    {
        if($eventType === self::EVENT_POST_HIDE) {
            return self::EVENT_POST_HIDE;
        }

        if($eventType === self::EVENT_POST_NOT_INTERESTED) {
            return self::EVENT_POST_NOT_INTERESTED;
        }

        if($eventType === self::EVENT_POST_QUICK_SKIP) {
            return self::EVENT_POST_QUICK_SKIP;
        }

        if($eventType === self::EVENT_POST_DWELL && $dwellSeconds < 2) {
            return self::EVENT_POST_QUICK_SKIP;
        }

        if($eventType === self::EVENT_POST_DWELL) {
            return self::EVENT_POST_DWELL;
        }

        return self::EVENT_POST_IMPRESSION;
    }

    private function dwellSeconds(array $payload): float
    {
        $value = data_get($payload, 'dwell_time_seconds', data_get($payload, 'watch_time_seconds', 0));

        return is_numeric($value) ? max(0.0, min(86400.0, (float) $value)) : 0.0;
    }
}
