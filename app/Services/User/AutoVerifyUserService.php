<?php

namespace App\Services\User;

use App\Models\User;
use App\Services\Ad\AdRewardService;

class AutoVerifyUserService
{
    public function enabled(): bool
    {
        return (bool) config('features.auto_verification.enabled', false);
    }

    public function verifyIfEnabled(?User $user): bool
    {
        if (! $user || ! $this->enabled()) {
            return false;
        }

        if ($user->verified && ! empty($user->verified_at)) {
            return false;
        }

        $user->forceFill([
            'verified' => true,
            'verified_at' => $user->verified_at ?: now(),
        ])->save();

        app(AdRewardService::class)->grantCurrentMonth($user->refresh());

        return true;
    }
}
