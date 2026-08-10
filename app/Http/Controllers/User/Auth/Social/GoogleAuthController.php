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

        if(! $result['exists']) {
            $newUser = $result['user'];

            $this->hydrateCreatedUserProfile($newUser, $result['socialiteUser']);

            Auth::login($newUser);

            app(AutoVerifyUserService::class)->verifyIfEnabled($newUser);
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
        $username = $this->resolveAvailableUsername($socialiteUser, $user->id);
        $userAvatarFilePath = config('user.avatar');
        $userPicture = data_get($socialiteUser->user, 'picture');

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
            'first_name' => data_get($socialiteUser->user, 'given_name', $user->first_name),
            'last_name' => data_get($socialiteUser->user, 'family_name', $user->last_name),
            'email' => data_get($socialiteUser->user, 'email', $user->email),
            'avatar' => $userAvatarFilePath,
        ]);
    }

    private function resolveAvailableUsername($socialiteUser, int $ignoreUserId): string
    {
        $nickname = trim((string) $socialiteUser->nickname);
        $emailLocalPart = Str::before((string) data_get($socialiteUser->user, 'email', ''), '@');

        $baseUsername = $nickname !== ''
            ? $nickname
            : join('_', array_filter([$emailLocalPart, $this->driverName]));

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
}
