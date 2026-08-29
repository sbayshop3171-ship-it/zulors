<?php

namespace App\Http\Controllers\Api\Auth;

use App\Models\NativeAuthSession;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Str;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Laravel\Socialite\Two\User as SocialiteUser;
use App\Enums\Flash\FlashType;
use App\Support\Views\Flash;
use App\Services\User\AutoVerifyUserService;
use App\Services\Auth\Social\SocialAuthService;
use App\Services\Auth\Social\GoogleIdTokenVerifier;
use App\Services\Auth\Social\GoogleSocialProfileService;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Throwable;

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

        try {
            $credentials = $socialAuthService->setDriver(self::DRIVER)->getCredentials();
            $googlePayload = $googleIdTokenVerifier->verify(
                $validatedData['id_token'],
                $this->trustedAudiences((string) $credentials['client_id'])
            );
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
                'status' => $user->requiresOnboarding() ? 'onboarding' : 'authenticated',
                'is_existing_user' => (bool) $result['exists'],
                'next_url' => $this->resolvePostAuthRedirectUrl($user),
                'redirect_url' => route('social-login.google.native.consume', [
                    'token' => $handoffToken,
                ]),
            ])->header('Cache-Control', 'no-store');
        } catch (ValidationException|HttpExceptionInterface $exception) {
            if($exception instanceof HttpExceptionInterface && $exception->getStatusCode() === 404) {
                return response()->json([
                    'message' => __('Google sign in is not available right now. Please use email login.'),
                    'errors' => [
                        'google' => [__('Google sign in is not available right now. Please use email login.')],
                    ],
                    'code' => 'google_auth_unavailable',
                ], 422)->header('Cache-Control', 'no-store');
            }

            throw $exception;
        } catch (Throwable $exception) {
            report($exception);

            return response()->json([
                'message' => __('We could not complete Google sign in. Please try again.'),
                'errors' => [
                    'google' => [__('We could not complete Google sign in. Please try again.')],
                ],
                'code' => 'google_auth_failed',
            ], 422);
        }
    }

    public function consume(Request $request, string $token)
    {
        $nativeAuthSession = DB::transaction(function () use ($token) {
            $session = NativeAuthSession::query()
                ->with('user')
                ->where('provider_name', self::DRIVER)
                ->where('token_hash', hash('sha256', $token))
                ->whereNull('consumed_at')
                ->where('expires_at', '>', now())
                ->lockForUpdate()
                ->first();

            if($session) {
                $session->forceFill([
                    'consumed_at' => now(),
                ])->save();
            }

            return $session;
        });

        if(! $nativeAuthSession || ! $nativeAuthSession->user) {
            return redirect()->route('user.auth.index')
                ->with('flashMessage', (new Flash(
                    content: __('We could not complete Google sign in. Please start again.'),
                    type: FlashType::ERROR,
                ))->get());
        }

        Auth::login($nativeAuthSession->user, true);
        $request->session()->regenerate();

        return redirect()->to($this->resolvePostAuthRedirectUrl($nativeAuthSession->user));
    }

    private function makeSocialiteUser(array $googlePayload): SocialiteUser
    {
        return (new SocialiteUser())
            ->map([
                'id' => (string) data_get($googlePayload, 'sub'),
                'nickname' => null,
                'name' => (string) data_get($googlePayload, 'name', ''),
                'email' => mb_strtolower(trim((string) data_get($googlePayload, 'email'))),
                'avatar' => data_get($googlePayload, 'picture'),
            ])
            ->setRaw([
                'email' => mb_strtolower(trim((string) data_get($googlePayload, 'email'))),
                'email_verified' => data_get($googlePayload, 'email_verified', true),
                'given_name' => (string) data_get($googlePayload, 'given_name', ''),
                'family_name' => (string) data_get($googlePayload, 'family_name', ''),
                'name' => (string) data_get($googlePayload, 'name', ''),
                'picture' => data_get($googlePayload, 'picture'),
            ]);
    }

    private function resolvePostAuthRedirectUrl(User $user): string
    {
        return $user->requiresOnboarding()
            ? route('user.onboarding.index', 'profile')
            : route('user.desktop.index');
    }

    /**
     * @return array<int, string>
     */
    private function trustedAudiences(string $browserClientId): array
    {
        return array_values(array_unique(array_filter(array_merge(
            [$browserClientId],
            config('services.google.native_client_ids', [])
        ))));
    }
}
