<?php

namespace App\Services\Ad;

use App\Models\User;
use App\Models\UserAdInterestScore;
use App\Services\Timeline\TopicExtractionService;
use Illuminate\Support\Carbon;

class UserAdInterestService
{
    public function __construct(private AdTopicPolicy $topicPolicy)
    {
    }

    public function isEnabled(?User $user): bool
    {
        if(! $user || ! config('ads.personalization.enabled', true) || ! config('ads.personalization.ranking_enabled', false)) {
            return false;
        }

        $age = $this->age($user);
        return $age !== null && $age >= 18 && (bool) $user->adPreference?->personalized_ads;
    }

    public function record(User|int $user, string $topic, float $delta): void
    {
        $userModel = $user instanceof User ? $user : User::query()->find($user);
        $normalized = $this->topicPolicy->normalizeAllowed($topic);

        if(! $this->isEnabled($userModel) || ! $normalized || $delta == 0.0) {
            return;
        }

        $score = UserAdInterestScore::query()->firstOrCreate([
            'user_id' => $userModel->id,
            'topic' => $normalized,
        ], [
            'score' => 0,
            'events_count' => 0,
        ]);

        $currentScore = $this->decayedScore($score);
        $score->score = max(-100, min(1000, $currentScore + $delta));
        $score->events_count++;
        $score->last_event_at = now();
        $score->save();
    }

    public function recordSearch(User $user, string $query): ?string
    {
        $topic = $this->topicPolicy->normalizeAllowed($query);
        if(! $topic || mb_strlen($topic) < 3) {
            return null;
        }

        $this->record($user, $topic, 4.0);

        return $topic;
    }

    public function scores(User $user, array $topics = []): array
    {
        if(! $this->isEnabled($user)) {
            return [];
        }

        $scores = UserAdInterestScore::query()->where('user_id', $user->id)
            ->when($topics, fn($query) => $query->whereIn('topic', $topics))
            ->where('last_event_at', '>=', now()->subDays(90))
            ->get();

        return $scores->mapWithKeys(fn(UserAdInterestScore $score) => [
            $score->topic => $this->decayedScore($score),
        ])->all();
    }

    public function forget(User $user): void
    {
        $user->adInterestScores()->delete();
    }

    public function age(User $user): ?int
    {
        if(! $user->birth_year || ! $user->birth_month || ! $user->birth_day) {
            return null;
        }

        try {
            $birth = Carbon::create((int) $user->birth_year, (int) $user->birth_month, (int) $user->birth_day);
            return $birth?->age;
        } catch(\Throwable) {
            return null;
        }
    }

    private function decayedScore(UserAdInterestScore $score): float
    {
        if(! $score->last_event_at) {
            return (float) $score->score;
        }

        $days = max(0, $score->last_event_at->diffInHours(now()) / 24);
        return (float) $score->score * pow($score->score < 0 ? 0.90 : 0.96, $days);
    }
}
