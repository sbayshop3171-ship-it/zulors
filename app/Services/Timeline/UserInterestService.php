<?php

namespace App\Services\Timeline;

use App\Models\Post;
use App\Models\User;
use App\Models\UserInterestScore;
use Illuminate\Support\Carbon;

class UserInterestService
{
    public const EVENT_VIEW = 'view';
    public const EVENT_REACTION = 'reaction';
    public const EVENT_COMMENT = 'comment';
    public const EVENT_BOOKMARK = 'bookmark';
    public const EVENT_SHARE = 'share';
    public const EVENT_REPORT = 'report';
    public const EVENT_HIDE = 'hide';

    public const EVENT_WEIGHTS = [
        self::EVENT_VIEW => 1.0,
        self::EVENT_REACTION => 4.0,
        self::EVENT_COMMENT => 8.0,
        self::EVENT_BOOKMARK => 10.0,
        self::EVENT_SHARE => 12.0,
        self::EVENT_REPORT => -24.0,
        self::EVENT_HIDE => -14.0,
    ];

    private const POSITIVE_DECAY_RATE = 0.96;
    private const NEGATIVE_DECAY_RATE = 0.90;
    private const MIN_SCORE = -100.0;
    private const MAX_SCORE = 1000.0;

    public function __construct(private TopicExtractionService $topicExtractionService)
    {
    }

    public function recordPostInteraction(User|int $user, Post $post, string $eventType, ?float $delta = null): void
    {
        $userId = $user instanceof User ? $user->id : $user;
        $delta = $delta ?? data_get(self::EVENT_WEIGHTS, $eventType, 0.0);

        if(empty($userId) || $delta == 0.0) {
            return;
        }

        $topics = $this->topicExtractionService->syncPostTopics($post);

        $topics->each(function($topic) use ($userId, $delta) {
            $this->applyScore($userId, $topic->topic, $delta * (float) $topic->weight);
        });
    }

    public function applyScore(int $userId, string $topic, float $delta): ?UserInterestScore
    {
        $topic = $this->topicExtractionService->normalizeTopic($topic);

        if(empty($topic)) {
            return null;
        }

        $interestScore = UserInterestScore::query()->firstOrCreate([
            'user_id' => $userId,
            'topic' => $topic,
        ], [
            'score' => 0,
            'events_count' => 0,
            'positive_events_count' => 0,
            'negative_events_count' => 0,
            'last_event_at' => null,
        ]);

        $currentScore = $this->decayedScore($interestScore);
        $nextScore = max(self::MIN_SCORE, min(self::MAX_SCORE, $currentScore + $delta));

        $interestScore->score = round($nextScore, 4);
        $interestScore->events_count = $interestScore->events_count + 1;
        $interestScore->positive_events_count = $interestScore->positive_events_count + ($delta > 0 ? 1 : 0);
        $interestScore->negative_events_count = $interestScore->negative_events_count + ($delta < 0 ? 1 : 0);
        $interestScore->last_event_at = now();
        $interestScore->save();

        return $interestScore;
    }

    public function scoresForTopics(User $user, array $topics): array
    {
        $topics = collect($topics)
            ->map(fn($topic) => $this->topicExtractionService->normalizeTopic($topic))
            ->filter()
            ->unique()
            ->values();

        if($topics->isEmpty()) {
            return [];
        }

        return UserInterestScore::query()
            ->where('user_id', $user->id)
            ->whereIn('topic', $topics->all())
            ->get()
            ->mapWithKeys(fn(UserInterestScore $score) => [
                $score->topic => round($this->decayedScore($score), 4),
            ])
            ->all();
    }

    public function decayAll(): int
    {
        $updatedCount = 0;

        UserInterestScore::query()->chunkById(500, function($scores) use (&$updatedCount) {
            $scores->each(function(UserInterestScore $score) use (&$updatedCount) {
                $score->score = round($this->decayedScore($score), 4);
                $score->save();

                $updatedCount++;
            });
        });

        return $updatedCount;
    }

    public function decayedScore(UserInterestScore $interestScore): float
    {
        $lastSignalAt = $interestScore->last_event_at ?: $interestScore->updated_at ?: now();
        $lastSignalAt = $lastSignalAt instanceof Carbon ? $lastSignalAt : Carbon::parse($lastSignalAt);
        $daysSinceSignal = max(0, $lastSignalAt->diffInHours(now()) / 24);
        $decayRate = $interestScore->score < 0 ? self::NEGATIVE_DECAY_RATE : self::POSITIVE_DECAY_RATE;

        return (float) $interestScore->score * pow($decayRate, $daysSinceSignal);
    }
}
