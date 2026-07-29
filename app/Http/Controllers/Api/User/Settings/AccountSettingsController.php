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

namespace App\Http\Controllers\Api\User\Settings;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use App\Traits\Http\Api\SupportsApiResponses;
use App\Validation\User\Account\UserdataRules;
use App\Validation\User\Account\UserdataMessages;
use App\Services\Filesystem\Delete\FileDeleteService;
use App\Services\Filesystem\Upload\ImageUploadService;

class AccountSettingsController extends Controller
{
    use SupportsApiResponses;

    private $me;

    public function __construct() {
        $this->me = me();
    }

    public function getAccountSettings() {
        return $this->responseSuccess([
            'data' => [
                'validation_rules' => [
                    'bio' => config('user.validation.bio'),
                    'first_name' => config('user.validation.first_name'),
                    'last_name' => config('user.validation.last_name'),
                    'username' => config('user.validation.username'),
                    'website' => config('user.validation.website'),
                    'caption' => config('user.validation.caption'),
                ],
                'user_data' => [
                    'first_name' => $this->me->first_name,
                    'last_name' => $this->me->last_name,
                    'username' => $this->me->username,
                    'bio' => $this->me->bio,
                    'website' => $this->me->website,
                    'gender' => $this->me->gender,
                    'caption' => $this->me->getCaption()
                ]
            ]
        ]);
    }

    public function getCredentialsSettings()
    {
        return $this->responseSuccess([
            'data' => [
                'email' => $this->me->email,
                'phone' => empty($this->me->phone) ? null : $this->me->phone,
                'security_settings' => [
                    'login_notification' => $this->me->securitySettings->login_notification
                ]
            ]
        ]);
    }

    public function updateAccountData(Request $request)
    {
        $validator = Validator::make(data: [
            'first_name' => $request->get('first_name', ''),
            'gender' => $request->get('gender', ''),
            'bio' => $request->get('bio', ''),
            'website' => $request->get('website', ''),
            'username' => $request->get('username', ''),
            'last_name' => $request->get('last_name', ''),
            'caption' => $request->get('caption', '')
        ], rules: [
            'gender' => UserdataRules::gender(),
            'website' => UserdataRules::website(),
            'bio' => UserdataRules::bio(),
            'username' => UserdataRules::username(),
            'first_name' => UserdataRules::firstName(),
            'last_name' => UserdataRules::lastName(),
            'caption' => UserdataRules::caption()
        ], messages: [
            'username' => UserdataMessages::username(),
            'first_name' => UserdataMessages::firstName(),
            'last_name' => UserdataMessages::lastName(),
            'bio' => UserdataMessages::bio(),
            'website' => UserdataMessages::website(),
            'gender' => UserdataMessages::gender(),
            'caption' => UserdataMessages::caption()
        ]);

        if ($validator->fails()) {
            $this->throwValidationError($validator);
        }

        $validatedData = $validator->validated();

        $updateData = [
            'first_name' => $validatedData['first_name'],
            'last_name' => (empty($validatedData['last_name'])) ? '' : $validatedData['last_name'],
            'username' => $validatedData['username'],
            'gender' => (empty($validatedData['gender'])) ? 'not-specified' : $validatedData['gender'],
            'bio' => (empty($validatedData['bio'])) ? '' : $validatedData['bio'],
            'website' => (empty($validatedData['website'])) ? '' : $validatedData['website'],
            'caption' => (empty($validatedData['caption'])) ? '' : $validatedData['caption'],
        ];

        $this->me->update($updateData);

        return $this->responseSuccess([
            'data' => $updateData
        ]);
    }

    public function updateTheme(Request $request)
    {
        $request->validate([
            'theme' => ['required', 'string', Rule::in(['light', 'dark', 'system'])],
            'resolved_theme' => ['nullable', 'string', Rule::in(['light', 'dark'])]
        ]);

        if(config('features.dark_theme.enabled')) {
            $previousTheme = $this->me->theme;
            $resolvedTheme = store_theme_preference(
                $request->input('theme'),
                $request->input('resolved_theme')
            );

            return $this->responseSuccess([
                'data' => [
                    'theme' => [
                        'new_mode' => $request->input('theme'),
                        'resolved_mode' => $resolvedTheme,
                        'previous_mode' => $previousTheme
                    ]
                ]
            ]);
        }

        return $this->responseSuccess([
            'data' => [
                'theme' => [
                    'new_mode' => $request->input('theme'),
                    'resolved_mode' => theme_runtime_name($request->input('theme')),
                    'previous_mode' => $this->me->theme
                ]
            ]
        ]);
    }

    public function updateAvatar(Request $request)
    {
        $request->validate([
            'avatar' => ['required', 'image', config('user.validation.avatar.mimes'), config('user.validation.avatar.max')]
        ]);

        $imageUploadService = app(ImageUploadService::class);

        $fileDeleteService = app(FileDeleteService::class);

        $imageData = $imageUploadService->load($request->avatar->getRealPath())
            ->setStorageDisk(static_storage_disk())
            ->setNamespace('uploads/users/avatars')
            ->crop(config('user.avatar_config.crop_size'), config('user.avatar_config.crop_size'))
            ->compress(config('user.avatar_config.compress_rate'))
            ->upload();

        if(! empty($imageData)) {
            if(! me()->hasDefaultAvatar() && ! empty($this->me->avatar)) {
                $fileDeleteService->setStorageDisk(static_storage_disk())->deleteFile($this->me->avatar);
            }

            $this->me->update([
                'avatar' => $imageData['image_path']
            ]);

            return $this->responseSuccess([
                'data' => [
                    'avatar_url' => storage_url($imageData['image_path'], static_storage_disk())
                ]
            ]);
        }
    }

    public function updateCover(Request $request)
    {
        $request->validate([
            'cover' => ['required', 'image', config('user.validation.cover.mimes'), config('user.validation.cover.max')]
        ]);

        $imageUploadService = app(ImageUploadService::class);

        $fileDeleteService = app(FileDeleteService::class);

        $imageData = $imageUploadService->load($request->cover->getRealPath())
            ->setStorageDisk(static_storage_disk())
            ->setNamespace('uploads/users/covers')
            ->crop(config('user.cover_config.width'), config('user.cover_config.height'))
            ->compress(config('user.cover_config.compress_rate'))
            ->upload();

        if(! empty($imageData)) {
            if(! me()->hasDefaultCover() && ! empty($this->me->cover)) {
                $fileDeleteService->setStorageDisk(static_storage_disk())->deleteFile($this->me->cover);
            }

            $this->me->update([
                'cover' => $imageData['image_path']
            ]);

            return $this->responseSuccess([
                'data' => [
                    'cover_url' => storage_url($imageData['image_path'], static_storage_disk())
                ]
            ]);
        }
    }

    public function getLinkedAccounts()
    {
        $linkedAccounts = $this->fetchLinkedUsers();
        $masterLabel = __('labels.master_account');

        return $this->responseSuccess([
            'data' => $linkedAccounts->map(function($userData) use ($masterLabel) {
                return [
                    'id' => $userData->id,
                    'name' => ($userData->isMasterAccount()) ? "{$userData->name} ({$masterLabel})" : $userData->name,
                    'avatar_url' => $userData->avatar_url,
                    'username' => $userData->username,
                    'verified' => $userData->isVerified(),
                    'caption' => $userData->getCaption(),
                    'bio' => $userData->bio,
                    'is_active' => ($userData->id === $this->me->id),
                    'website' => $userData->website,
                    'is_master_account' => $userData->isMasterAccount()
                ];
            })
        ]);
    }

    public function switchAccount(Request $request)
    {
        $request->validate([
            'account_id' => ['required', 'integer']
        ]);

        $accountId = $request->get('account_id');
        $linkedAccounts = $this->fetchLinkedUsers();
        $accountData = $linkedAccounts->where('id', $accountId)->first();

        // Prevent switching if it's disabled.

        if(! config('features.switch_account.enabled')) {
            return $this->responseSuccess([
                'data' => null
            ]);
        }

        // TODO:
        // For mobile API add sanctum token refresh
        // and handle switch account in mobile app

        if($accountData && $accountData->id !== $this->me->id) {
            Auth::guard('web')->logout();
            Auth::guard('web')->login($accountData, true);

            return $this->responseSuccess([
                'data' => null
            ]);
        }

        else {
            $this->responseResourceNotFoundError('User', $accountId);
        }
    }

    private function fetchLinkedUsers()
    {
        $linkedAccounts = collect([
            $this->me
        ]);

        if($this->me->isMasterAccount()) {
            $linkedAccounts = $linkedAccounts->concat($this->me->linkedAccounts);
        }

        else {
            $masterAccount = $this->me->masterAccount;


            if($masterAccount) {
                $masterLinkedAccounts = $masterAccount->linkedAccounts()->excludeSelf()->get();
                $masterLinkedAccounts->push($masterAccount);

                $linkedAccounts = $linkedAccounts->concat($masterLinkedAccounts);
            }
        }

        return $linkedAccounts;
    }

    public function getAuthorshipSettings()
    {
        if(me()->isAuthor()) {
            $authorshipStatus = 'authorized';
        }
        else {
            $requestData = $this->me->authorshipRequest;

            $authorshipStatus = ($requestData) ? $requestData->status->value : 'not_requested';
        }

        return $this->responseSuccess([
            'data' => [
                'status' => $authorshipStatus
            ]
        ]);
    }

    public function requestAuthorship()
    {
        $requestedData = $this->me->authorshipRequest;

        if(! $requestedData) {
            me()->authorshipRequest()->create([
                'user_id' => $this->me->id
            ]);
        }

        return $this->responseSuccess([
            'data' => null
        ]);
    }
}
