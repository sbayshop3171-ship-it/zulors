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

        if($result['exists']) {
            return redirect()->route('user.desktop.index');
        }

        else{
            $newUser = $result['user'];
            $socialiteUser = $result['socialiteUser'];

            $username = $socialiteUser->nickname;

            if(empty($username)) {
                $username = join('_', [Str::before($socialiteUser->user['email'], '@'), $this->driverName]);
            }

            $userAvatarFilePath = config('user.avatar');

            if(! empty($socialiteUser->user['picture'])) {

                // TODO: optimize
                // Move to image upload service.

                try {
                    $filename = Str::random(40) . '.jpeg';

                    $filepath = 'uploads/users/avatars/' . $filename;

                    $fileUploaded = Storage::disk(static_storage_disk())->put($filepath, file_get_contents($socialiteUser->user['picture']));

                    if($fileUploaded) {
                        $userAvatarFilePath = $filepath;
                    }
                } catch (Throwable $th) {
                    // Pass
                }
            }

            $newUser->update([
                'username' =>  $username,
                'caption' => "@{$username}",
                'first_name' => $socialiteUser->user['given_name'],
                'last_name' => $socialiteUser->user['family_name'],
                'email' => $socialiteUser->user['email'],
                'avatar' => $userAvatarFilePath
            ]);

            Auth::login($newUser);

            app(AutoVerifyUserService::class)->verifyIfEnabled($newUser);

            request()->session()->regenerate();

            return redirect()->route('user.desktop.index');
        }
    }

    private function fetchUserData()
    {
        return Socialite::buildProvider(GoogleProvider::class, $this->driverCredentials)->stateless()->user();
    }
}
