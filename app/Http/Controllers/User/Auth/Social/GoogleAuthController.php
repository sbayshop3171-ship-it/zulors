<?php
/*
|--------------------------------------------------------------------------
| Zulors - The Zulors Web Application.
|--------------------------------------------------------------------------
| Author: Mansur Terla. Full-Stack Web Developer, UI/UX Designer.
| Website: www.terla.me
| E-mail: mansurtl.contact@gmail.com
| Instagram: @mansur_terla
| Telegram: @mansurtl_contact
|--------------------------------------------------------------------------
| Copyright (c)  Zulors. All rights reserved.
|--------------------------------------------------------------------------
*/

namespace App\Http\Controllers\User\Auth\Social;

use Throwable;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\GoogleProvider;
use App\Services\Auth\Social\SocialAuthService;
use App\Services\User\AutoVerifyUserService;
use App\Services\Auth\Social\GoogleSocialProfileService;

class GoogleAuthController extends Controller
{
    protected $defaultScopes = ['email', 'profile'];

    protected array $driverCredentials;

    protected string $driverName = 'google';

    protected $socialAuthService;

    protected $googleSocialProfileService;

    public function __construct(SocialAuthService $socialAuthService, GoogleSocialProfileService $googleSocialProfileService)
    {
        $this->socialAuthService = $socialAuthService;
        $this->googleSocialProfileService = $googleSocialProfileService;
        $this->driverCredentials = $this->socialAuthService->setDriver($this->driverName)->getCredentials();
    }

    public function index()
    {
        $socialite = Socialite::buildProvider(GoogleProvider::class, $this->driverCredentials);

        return $socialite->scopes($this->defaultScopes)->redirect();
    }

    public function callbackHandler()
    {
        try {
            $socialiteUser = $this->fetchUserData();
        } catch (Throwable $exception) {
            report($exception);

            return redirect()->route('user.auth.index')->withErrors([
                'google' => __('We could not complete Google sign in. Please try again.'),
            ]);
        }

        $result = $this->socialAuthService->setDriver($this->driverName)->handle($socialiteUser);

        $user = $result['user'];

        if(! $result['exists'] || $this->googleSocialProfileService->shouldRefreshIncompleteProfile($user, $this->driverName)) {
            $this->googleSocialProfileService->syncUser($user, $result['socialiteUser'], $this->driverName);
        }

        if(! $result['exists']) {
            Auth::login($user, true);

            app(AutoVerifyUserService::class)->verifyIfEnabled($user);
        }

        request()->session()->regenerate();

        return redirect()->to($this->resolvePostAuthRedirectUrl($user));
    }

    private function fetchUserData()
    {
        return Socialite::buildProvider(GoogleProvider::class, $this->driverCredentials)->user();
    }

    private function resolvePostAuthRedirectUrl(\App\Models\User $user): string
    {
        return $user->requiresOnboarding()
            ? route('user.onboarding.index', 'profile')
            : route('user.desktop.index');
    }
}
