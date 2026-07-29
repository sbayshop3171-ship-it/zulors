<?php

namespace App\Services\Auth\Social;

use Throwable;
use App\Models\Onboard;
use App\Models\SocialAccount;
use App\Support\SocialLoginDrivers;
use Illuminate\Support\Facades\Auth;
use App\Actions\User\CreateUserAction;
use App\Services\Blacklist\BlacklistService;
use Laravel\Socialite\Two\User as SocialiteUser;

class SocialAuthService
{
    private string $driver;
    private string $socialUserId;
    private string|null $socialUserEmail;

    private BlacklistService $blacklistService;

    public function __construct(BlacklistService $blacklistService) {
        $this->blacklistService = $blacklistService;
    }

    public function handle(SocialiteUser $socialiteUser)
    {
        try {
            $driver = $this->driver;
            $this->socialUserId = $socialiteUser->getId();
            $this->socialUserEmail = $socialiteUser->getEmail();

            $this->restrictBlacklistedEmailOrSocialId();

            $socialAccount = SocialAccount::where('provider_id', $this->socialUserId)->first();

            if($socialAccount) {
                Auth::login($socialAccount->user);

                return [
                    'user' => $socialAccount->user,
                    'socialiteUser' => $socialiteUser,
                    'exists' => true
                ];
            }

            else {
                $now = time();

                $userData = [
                    'username' => "{$driver}_{$now}",
                    'email' => (empty($this->socialUserEmail)) ? "{$now}@$driver.com" : $this->socialUserEmail,
                ];

                $newUser = (new CreateUserAction($userData))->execute();

                $newUser->socialAccounts()->create([
                    'provider_name' => $driver,
                    'provider_id' => $this->socialUserId,
                ]);

                Onboard::create([
                    'user_id' => $newUser->id,
                    'step' => 'one'
                ]);

                return [
                    'user' => $newUser,
                    'socialiteUser' => $socialiteUser,
                    'exists' => false
                ];
            }


        } catch (Throwable $th) {
            throw $th;
        }
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
}
