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
use App\Models\User;
use Illuminate\Support\Str;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Contracts\Support\Arrayable;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\GoogleProvider;
use App\Services\Auth\Social\SocialAuthService;
use App\Services\User\AutoVerifyUserService;

class GoogleAuthController extends Controller
{
    protected $defaultScopes = ['email', 'profile'];

    protected array $driverCredentials;

    protected string $driverName = 'google';

    protected $socialAuthService;

    public function __construct(SocialAuthService $socialAuthService)
    {
        $this->socialAuthService = $socialAuthService;
        $this->driverCredentials = $this->socialAuthService->setDriver($this->driverName)->getCredentials();
    }

    public function index()
    {
        $socialite = Socialite::buildProvider(GoogleProvider::class, $this->driverCredentials);

        return $socialite->scopes($this->defaultScopes)->redirect();
    }

    public function callbackHandler()
    {
        $socialiteUser = $this->fetchUserData();

        $result = $this->socialAuthService->setDriver($this->driverName)->handle($socialiteUser);

        $user = $result['user'];

        if(! $result['exists'] || $this->shouldRefreshIncompleteGoogleProfile($user)) {
            $this->hydrateCreatedUserProfile($user, $result['socialiteUser']);
        }

        if(! $result['exists']) {
            Auth::login($user);

            app(AutoVerifyUserService::class)->verifyIfEnabled($user);
        }

        request()->session()->regenerate();

        return redirect()->route('user.desktop.index');
    }

    private function fetchUserData()
    {
        return Socialite::buildProvider(GoogleProvider::class, $this->driverCredentials)->stateless()->user();
    }

    private function hydrateCreatedUserProfile(User $user, $socialiteUser): void
    {
        $rawUser = $this->getRawUserData($socialiteUser);
        $profileNames = $this->resolveProfileNames($user, $socialiteUser, $rawUser);
        $username = $this->resolveAvailableUsername($socialiteUser, $user->id);
        $userAvatarFilePath = config('user.avatar');
        $userPicture = $socialiteUser->getAvatar() ?: data_get($rawUser, 'picture');

        if(! empty($userPicture)) {

            // TODO: optimize
            // Move to image upload service.

            try {
                $filename = Str::random(40) . '.jpeg';
                $filepath = 'uploads/users/avatars/' . $filename;
                $fileUploaded = Storage::disk(static_storage_disk())->put($filepath, file_get_contents($userPicture));

                if($fileUploaded) {
                    $userAvatarFilePath = $filepath;
                }
            } catch (Throwable $th) {
                // Pass
            }
        }

        $user->update([
            'username' => $username,
            'caption' => "@{$username}",
            'first_name' => $profileNames['first_name'],
            'last_name' => $profileNames['last_name'],
            'email' => $socialiteUser->getEmail() ?: data_get($rawUser, 'email', $user->email),
            'avatar' => $userAvatarFilePath,
            'email_verified_at' => $this->resolveEmailVerifiedAt($user, $rawUser),
        ]);
    }

    private function resolveAvailableUsername($socialiteUser, int $ignoreUserId): string
    {
        $rawUser = $this->getRawUserData($socialiteUser);
        $nickname = trim((string) ($socialiteUser->getNickname() ?: data_get($rawUser, 'nickname', '')));
        $email = (string) ($socialiteUser->getEmail() ?: data_get($rawUser, 'email', ''));
        $emailLocalPart = Str::before($email, '@');
        $displayName = trim((string) ($socialiteUser->getName() ?: data_get($rawUser, 'name', '')));

        $baseUsername = $nickname !== ''
            ? $nickname
            : ($emailLocalPart !== '' ? $emailLocalPart : $displayName);

        $baseUsername = preg_replace('/[^A-Za-z0-9_]+/', '_', Str::lower($baseUsername));
        $baseUsername = trim((string) $baseUsername, '_');

        if($baseUsername === '') {
            $baseUsername = "{$this->driverName}_user";
        }

        $candidate = Str::limit($baseUsername, 30, '');
        $suffix = 1;

        while(
            User::query()
                ->where('username', $candidate)
                ->where('id', '!=', $ignoreUserId)
                ->exists()
        ) {
            $suffixText = '_' . $suffix;
            $candidate = Str::limit($baseUsername, max(5, 30 - strlen($suffixText)), '') . $suffixText;
            $suffix += 1;
        }

        return $candidate;
    }

    private function getRawUserData($socialiteUser): array
    {
        $rawUser = $socialiteUser->user ?? [];

        if($rawUser instanceof Arrayable) {
            return $rawUser->toArray();
        }

        if(is_object($rawUser)) {
            return get_object_vars($rawUser);
        }

        return is_array($rawUser) ? $rawUser : [];
    }

    private function resolveProfileNames(User $user, $socialiteUser, array $rawUser): array
    {
        $fullName = trim((string) ($socialiteUser->getName() ?: data_get($rawUser, 'name', '')));
        $firstName = trim((string) data_get($rawUser, 'given_name', ''));
        $lastName = trim((string) data_get($rawUser, 'family_name', ''));

        if($firstName === '' && $fullName !== '') {
            $nameParts = preg_split('/\s+/', $fullName, 2) ?: [];
            $firstName = trim((string) ($nameParts[0] ?? ''));

            if($lastName === '') {
                $lastName = trim((string) ($nameParts[1] ?? ''));
            }
        }

        if($firstName === '') {
            $firstName = trim((string) $user->first_name);
        }

        if($firstName === '') {
            $firstName = Str::headline(Str::before($user->email ?: $user->username, '@'));
        }

        return [
            'first_name' => $firstName,
            'last_name' => $lastName !== '' ? $lastName : (string) $user->last_name,
        ];
    }

    private function resolveEmailVerifiedAt(User $user, array $rawUser)
    {
        if($user->email_verified_at) {
            return $user->email_verified_at;
        }

        return filter_var(data_get($rawUser, 'email_verified', false), FILTER_VALIDATE_BOOLEAN) ? now() : null;
    }

    private function shouldRefreshIncompleteGoogleProfile(User $user): bool
    {
        return Str::startsWith((string) $user->username, "{$this->driverName}_") && blank($user->first_name);
    }
}
