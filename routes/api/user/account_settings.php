<?php
/*
|--------------------------------------------------------------------------
| Zulors - The Ultimate Zulors Web Application.
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

use Illuminate\Support\Facades\Route;

Route::get('/languages', [App\Http\Controllers\Api\User\Settings\LanguageSettingsController::class, 'getLanguages']);
Route::put('/languages/switch', [App\Http\Controllers\Api\User\Settings\LanguageSettingsController::class, 'switchLanguage']);

Route::get('/email/settings', [App\Http\Controllers\Api\User\Settings\EmailSettingsController::class, 'getEmailSettings']);
Route::get('/email/check', [App\Http\Controllers\Api\User\Settings\EmailSettingsController::class, 'checkEmailRequest']);
Route::put('/email/update', [App\Http\Controllers\Api\User\Settings\EmailSettingsController::class, 'updateEmailAddress']);
Route::post('/email/update/confirm', [App\Http\Controllers\Api\User\Settings\EmailSettingsController::class, 'confirmEmailUpdate']);
Route::post('/email/update/resend', [App\Http\Controllers\Api\User\Settings\EmailSettingsController::class, 'resendEmailConfirmCode']);

Route::get('/phone/settings', [App\Http\Controllers\Api\User\Settings\PhoneSettingsController::class, 'getPhoneSettings']);
Route::get('/phone/check', [App\Http\Controllers\Api\User\Settings\PhoneSettingsController::class, 'checkPhoneRequest']);
Route::put('/phone/update', [App\Http\Controllers\Api\User\Settings\PhoneSettingsController::class, 'updatePhoneNumber']);
Route::post('/phone/update/confirm', [App\Http\Controllers\Api\User\Settings\PhoneSettingsController::class, 'confirmPhoneUpdate']);
Route::post('/phone/update/resend', [App\Http\Controllers\Api\User\Settings\PhoneSettingsController::class, 'resendPhoneConfirmCode']);

Route::get('/password/settings', [App\Http\Controllers\Api\User\Settings\PasswordSettingsController::class, 'getPasswordSettings']);
Route::get('/password/generate', [App\Http\Controllers\Api\User\Settings\PasswordSettingsController::class, 'generatePassword']);
Route::put('/password/update', [App\Http\Controllers\Api\User\Settings\PasswordSettingsController::class, 'updatePassword']);

Route::get('/notifications/push/settings', [App\Http\Controllers\Api\User\Settings\NotificationSettingsController::class, 'getPushSettings']);
Route::get('/notifications/email/settings', [App\Http\Controllers\Api\User\Settings\NotificationSettingsController::class, 'getEmailSettings']);
Route::put('/notifications/login/update', [App\Http\Controllers\Api\User\Settings\NotificationSettingsController::class, 'updateLoginNotification']);
Route::put('/notification/push/update', [App\Http\Controllers\Api\User\Settings\NotificationSettingsController::class, 'updatePushSettings']);
Route::put('/notification/email/update', [App\Http\Controllers\Api\User\Settings\NotificationSettingsController::class, 'updateEmailSettings']);
Route::post('/devices/push-token', [App\Http\Controllers\Api\User\Device\PushTokenController::class, 'store']);
Route::delete('/devices/push-token', [App\Http\Controllers\Api\User\Device\PushTokenController::class, 'destroy']);

Route::get('/personal/settings', [App\Http\Controllers\Api\User\Settings\PersonalInfoSettingsController::class, 'getPersonalInfoSettings']);
Route::get('/personal/birthdate', [App\Http\Controllers\Api\User\Settings\PersonalInfoSettingsController::class, 'getBirthdateSettings']);
Route::get('/personal/country', [App\Http\Controllers\Api\User\Settings\PersonalInfoSettingsController::class, 'getCountrySettings']);
Route::get('/personal/city', [App\Http\Controllers\Api\User\Settings\PersonalInfoSettingsController::class, 'getCitySettings']);
Route::put('/personal/birthdate/update', [App\Http\Controllers\Api\User\Settings\PersonalInfoSettingsController::class, 'updateBirthdateSettings']);
Route::put('/personal/country/update', [App\Http\Controllers\Api\User\Settings\PersonalInfoSettingsController::class, 'updateCountrySettings']);
Route::put('/personal/city/update', [App\Http\Controllers\Api\User\Settings\PersonalInfoSettingsController::class, 'updateCitySettings']);

Route::get('/sessions', [App\Http\Controllers\Api\User\Settings\SessionSettingsController::class, 'getSessions']);
Route::delete('/sessions/terminate/other', [App\Http\Controllers\Api\User\Settings\SessionSettingsController::class, 'terminateOtherSessions']);

Route::get('/privacy/settings', [App\Http\Controllers\Api\User\Settings\PrivacySettingsController::class, 'getPrivacySettings']);
Route::put('/privacy/update', [App\Http\Controllers\Api\User\Settings\PrivacySettingsController::class, 'updatePrivacySettings']);

Route::get('/social/settings', [App\Http\Controllers\Api\User\Settings\SocialSettingsController::class, 'getSocialSettings']);
Route::put('/social/update', [App\Http\Controllers\Api\User\Settings\SocialSettingsController::class, 'updateSocialSettings']);

Route::get('/account/settings', [App\Http\Controllers\Api\User\Settings\AccountSettingsController::class, 'getAccountSettings']);
Route::put('/account/update', [App\Http\Controllers\Api\User\Settings\AccountSettingsController::class, 'updateAccountData']);

Route::get('/authorship/settings', [App\Http\Controllers\Api\User\Settings\AccountSettingsController::class, 'getAuthorshipSettings']);
Route::post('/authorship/request', [App\Http\Controllers\Api\User\Settings\AccountSettingsController::class, 'requestAuthorship']);
Route::get('/account/credentials/settings', [App\Http\Controllers\Api\User\Settings\AccountSettingsController::class, 'getCredentialsSettings']);
Route::get('/account/linked', [App\Http\Controllers\Api\User\Settings\AccountSettingsController::class, 'getLinkedAccounts']);
Route::post('/account/switch', [App\Http\Controllers\Api\User\Settings\AccountSettingsController::class, 'switchAccount']);
Route::post('/account/avatar/update', [App\Http\Controllers\Api\User\Settings\AccountSettingsController::class, 'updateAvatar']);
Route::post('/account/cover/update', [App\Http\Controllers\Api\User\Settings\AccountSettingsController::class, 'updateCover']);
Route::put('/account/theme/update', [App\Http\Controllers\Api\User\Settings\AccountSettingsController::class, 'updateTheme']);
Route::delete('/account/delete', [App\Http\Controllers\Api\User\Settings\DeleteAccountController::class, 'deleteAccount']);
