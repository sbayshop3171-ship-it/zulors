<?php

namespace App\Services\Auth\Social;

use App\Models\Onboard;
use App\Models\User;
use App\Models\SocialAccount;
use App\Support\SocialLoginDrivers;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Database\QueryException;
use App\Actions\User\CreateUserAction;
use App\Services\User\AutoVerifyUserService;
use App\Services\Blacklist\BlacklistService;
use Laravel\Socialite\Two\User as SocialiteUser;

class SocialAuthService
{
    private string $driver;
    private string $socialUserId;
    private string|null $socialUserEmail;
    private bool $loginResolvedUsers = true;

    private BlacklistService $blacklistService;

    public function __construct(BlacklistService $blacklistService) {
        $this->blacklistService = $blacklistService;
    }

    public function handle(SocialiteUser $socialiteUser)
    {
        $driver = $this->driver;
        $this->socialUserId = (string) $socialiteUser->getId();
        $this->socialUserEmail = $socialiteUser->getEmail();

        $this->restrictBlacklistedEmailOrSocialId();

        $socialAccount = SocialAccount::query()
            ->where('provider_name', $driver)
            ->where('provider_id', $this->socialUserId)
            ->first();

        if($socialAccount) {
            $this->loginUser($socialAccount->user);

            return [
                'user' => $socialAccount->user,
                'socialiteUser' => $socialiteUser,
                'exists' => true,
            ];
        }

        $existingUser = $this->existingUserByEmail();

        if($existingUser) {
            $socialAccount = $this->linkSocialAccountToUser($existingUser, $driver);
            $resolvedUser = $socialAccount->user ?: $existingUser;

            $this->loginUser($resolvedUser);

            return [
                'user' => $resolvedUser,
                'socialiteUser' => $socialiteUser,
                'exists' => true,
            ];
        }

        try {
            $newUser = DB::transaction(function() use ($driver) {
                $now = time();

                $userData = [
                    'username' => "{$driver}_{$now}",
                    'email' => empty($this->socialUserEmail) ? "{$now}@{$driver}.com" : $this->socialUserEmail,
                ];

                $createdUser = (new CreateUserAction($userData))->execute();

                $createdUser->socialAccounts()->create([
                    'provider_name' => $driver,
                    'provider_id' => $this->socialUserId,
                ]);

                Onboard::create([
                    'user_id' => $createdUser->id,
                    'step' => 'one',
                ]);

                return $createdUser;
            });
        } catch (QueryException $exception) {
            if(! $this->isSocialIdentityConflict($exception)) {
                throw $exception;
            }

            $existingSocialAccount = SocialAccount::query()
                ->where('provider_name', $driver)
                ->where('provider_id', $this->socialUserId)
                ->first();

            if(! $existingSocialAccount) {
                throw $exception;
            }

            $this->loginUser($existingSocialAccount->user);

            return [
                'user' => $existingSocialAccount->user,
                'socialiteUser' => $socialiteUser,
                'exists' => true,
            ];
        }

        return [
            'user' => $newUser,
            'socialiteUser' => $socialiteUser,
            'exists' => false,
        ];
    }

    public function getCredentials(): array
    {
        $socialLoginDrivers = new SocialLoginDrivers();

        $driverData = $socialLoginDrivers->getDriver($this->driver);

        if(empty($driverData['enabled'])) {
            abort(404);
        }

        return $driverData['credentials'];
    }

    public function setDriver(string $driver): self
    {
        $this->driver = $driver;

        return $this;
    }

    public function withoutLogin(): self
    {
        $this->loginResolvedUsers = false;

        return $this;
    }

    private function restrictBlacklistedEmailOrSocialId()
    {
        // TOD0
        // Add social account check also to prevent access from
        // Social platforms that does not provide emails.
        // If user is not blocked by IP, check if user is blocked by
        // Social user ID. Since it's unique on selected platform for each user.

        $isEmailBlacklisted = $this->blacklistService->isEmailBlacklisted($this->socialUserEmail);

        if($isEmailBlacklisted) {
            abort(403, __('auth.email_blocked'));
        }
    }

    private function existingUserByEmail(): ?User
    {
        $email = trim((string) $this->socialUserEmail);

        if($email === '') {
            return null;
        }

        return User::query()
            ->whereRaw('LOWER(email) = ?', [mb_strtolower($email)])
            ->first();
    }

    private function linkSocialAccountToUser(User $user, string $driver): SocialAccount
    {
        try {
            return DB::transaction(function() use ($user, $driver) {
                $socialAccount = $user->socialAccounts()->firstOrCreate([
                    'provider_name' => $driver,
                    'provider_id' => $this->socialUserId,
                ]);

                $socialAccount->setRelation('user', $user);

                return $socialAccount;
            });
        } catch (QueryException $exception) {
            if(! $this->isSocialIdentityConflict($exception)) {
                throw $exception;
            }

            $existingSocialAccount = SocialAccount::query()
                ->with('user')
                ->where('provider_name', $driver)
                ->where('provider_id', $this->socialUserId)
                ->first();

            if(! $existingSocialAccount) {
                throw $exception;
            }

            return $existingSocialAccount;
        }
    }

    private function loginUser(User $user): void
    {
        if(! $this->loginResolvedUsers) {
            return;
        }

        Auth::guard('web')->login($user, true);

        app(AutoVerifyUserService::class)->verifyIfEnabled($user);
    }

    private function isSocialIdentityConflict(QueryException $exception): bool
    {
        $sqlState = (string) $exception->getCode();
        $driverCode = (int) data_get($exception->errorInfo, 1, 0);

        return $sqlState === '23000' || $sqlState === '23505' || $driverCode === 1062;
    }
}
