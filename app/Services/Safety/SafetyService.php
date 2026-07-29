<?php

namespace App\Services\Safety;

use App\Models\Post;
use App\Models\User;
use App\Models\UserSafetyScore;

class SafetyService
{
    public function userSafety(User|int $user): UserSafetyScore
    {
        $userId = $user instanceof User ? $user->id : $user;

        return UserSafetyScore::query()->firstOrCreate([
            'user_id' => $userId,
        ], [
            'trust_score' => 100,
            'spam_score' => 0,
            'post_burst_count' => 0,
            'content_reports_count' => 0,
        ]);
    }

    public function isFrozen(User $user): bool
    {
        $safety = $this->userSafety($user);

        return $safety->frozen_until && $safety->frozen_until->isFuture();
    }

    public function freezeRemainingSeconds(User $user): int
    {
        $safety = $this->userSafety($user);

        if(empty($safety->frozen_until) || $safety->frozen_until->isPast()) {
            return 0;
        }

        return max(1, now()->diffInSeconds($safety->frozen_until));
    }

    public function recordPostCreated(User $user): UserSafetyScore
    {
        $windowStartedAt = now()->subSeconds((int) config('safety.posting.burst_window_seconds', 60));
        $safety = $this->userSafety($user);
        $recentPostCount = $user->posts()->where('created_at', '>=', $windowStartedAt->toDateTimeString())->count();
        $rollingCount = ($safety->updated_at && $safety->updated_at->gte($windowStartedAt)) ? ($safety->post_burst_count + 1) : 1;
        $burstCount = max($recentPostCount, $rollingCount);

        $safety->post_burst_count = $burstCount;

        if($burstCount >= (int) config('safety.posting.burst_threshold', 10)) {
            $safety->spam_score = min(100, $safety->spam_score + 18);
            $safety->trust_score = max(0, $safety->trust_score - 15);
            $safety->frozen_until = now()->addMinutes((int) config('safety.posting.freeze_minutes', 15));
            $safety->last_violation_at = now();
        }

        $safety->save();

        return $safety;
    }

    public function recordContentReport(Post $post): UserSafetyScore
    {
        $safety = $this->userSafety($post->user_id);
        $safety->content_reports_count = $safety->content_reports_count + 1;
        $safety->spam_score = min(100, $safety->spam_score + (float) config('safety.content.report_spam_score', 8));
        $safety->trust_score = max(0, $safety->trust_score - (float) config('safety.content.report_trust_penalty', 6));
        $safety->last_violation_at = now();
        $safety->save();

        return $safety;
    }

    public function authorSafetyPenalty(Post $post): float
    {
        $safety = $post->user?->safetyScore;

        if(empty($safety)) {
            return 0.0;
        }

        $freezePenalty = ($safety->frozen_until && $safety->frozen_until->isFuture()) ? 18.0 : 0.0;

        return min(60, ($safety->spam_score * 0.55) + ((100 - $safety->trust_score) * 0.18) + $freezePenalty);
    }
}
