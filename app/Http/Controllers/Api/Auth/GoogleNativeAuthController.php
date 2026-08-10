<?php

namespace App\Http\Controllers\Api\Auth;

use App\Models\NativeAuthSession;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use Laravel\Socialite\Two\User as SocialiteUser;
use App\Services\User\AutoVerifyUserService;
use App\Services\Auth\Social\SocialAuthService;
use App\Services\Auth\Social\GoogleIdTokenVerifier;
use App\Services\Auth\Social\GoogleSocialProfileService;

class GoogleNativeAuthController extends Controller
{
    private const DRIVER = 'google';
    private const HANDOFF_TTL_MINUTES = 5;

    public function issue(
        Request $request,
        SocialAuthService $socialAuthService,
        GoogleIdTokenVerifier $googleIdTokenVerifier,
        GoogleSocialProfileService $googleSocialProfileService
    ) {
        $validatedData = $request->validate([
            'id_token' => ['required', 'string', 'min:20', 'max:8000'],
        ]);

        $credentials = $socialAuthService->setDriver(self::DRIVER)->getCredentials();
        $googlePayload = $googleIdTokenVerifier->verify($validatedData['id_token'], (string) $credentials['client_id']);
        $socialiteUser = $this->makeSocialiteUser($googlePayload);

        $result = $socialAuthService
            ->setDriver(self::DRIVER)
            ->withoutLogin()
            ->handle($socialiteUser);

        $user = $result['user'];

        if(! $result['exists'] || $googleSocialProfileService->shouldRefreshIncompleteProfile($user, self::DRIVER)) {
            $googleSocialProfileService->syncUser($user, $result['socialiteUser'], self::DRIVER);
        }

        app(AutoVerifyUserService::class)->verifyIfEnabled($user);

        $handoffToken = Str::random(64);

        NativeAuthSession::query()->create([
            'user_id' => $user->id,
            'provider_name' => self::DRIVER,
            'token_hash' => hash('sha256', $handoffToken),
            'expires_at' => now()->addMinutes(self::HANDOFF_TTL_MINUTES),
            'ip_address' => $request->ip(),
            'user_agent' => Str::limit((string) $request->userAgent(), 512, ''),
        ]);

        return response()->json([
            'redirect_url' => route('social-login.google.native.consume', [
                'token' => $handoffToken,
            ]),
        ]);
    }

    public function consume(Request $request, string $token)
    {
        $nativeAuthSession = NativeAuthSession::query()
            ->with('user')
            ->where('provider_name', self::DRIVER)
            ->where('token_hash', hash('sha256', $token))
            ->whereNull('consumed_at')
            ->where('expires_at', '>', now())
            ->first();

        if(! $nativeAuthSession || ! $nativeAuthSession->user) {
            return redirect()->route('user.auth.index')->withErrors([
                'google' => __('We could not complete Google sign in. Please try again.'),
            ]);
        }

        $nativeAuthSession->forceFill([
            'consumed_at' => now(),
        ])->save();

        Auth::login($nativeAuthSession->user, true);
        $request->session()->regenerate();

        return redirect()->route('user.desktop.index');
    }

    private function makeSocialiteUser(array $googlePayload): SocialiteUser
    {
        return (new SocialiteUser())
            ->map([
                'id' => (string) data_get($googlePayload, 'sub'),
                'nickname' => null,
                'name' => (string) data_get($googlePayload, 'name', ''),
                'email' => (string) data_get($googlePayload, 'email'),
                'avatar' => data_get($googlePayload, 'picture'),
            ])
            ->setRaw([
                'email' => (string) data_get($googlePayload, 'email'),
                'email_verified' => data_get($googlePayload, 'email_verified', true),
                'given_name' => (string) data_get($googlePayload, 'given_name', ''),
                'family_name' => (string) data_get($googlePayload, 'family_name', ''),
                'name' => (string) data_get($googlePayload, 'name', ''),
                'picture' => data_get($googlePayload, 'picture'),
            ]);
    }
}
