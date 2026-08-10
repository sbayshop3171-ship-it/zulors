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

use App\Models\User;
use App\Http\Controllers\Api\Auth\GoogleNativeAuthController;
use App\Http\Controllers\Api\Push\PushActionController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Route;
use Illuminate\Validation\ValidationException;

$lightApiThrottle = 'throttle:180,1';
$readApiThrottle = 'throttle:300,1';
$interactiveApiThrottle = 'throttle:240,1';
$uploadApiThrottle = 'throttle:720,1';

Route::post('/sanctum/token', function (Request $request) {
    $request->validate([
        'email' => 'required|email',
        'password' => 'required',
        'device_name' => 'required',
    ]);

    $user = User::where('email', $request->email)->first();

    if (! $user || ! Hash::check($request->password, $user->password)) {
        throw ValidationException::withMessages([
            'email' => ['The provided credentials are incorrect.'],
        ]);
    }

    return $user->createToken($request->device_name)->plainTextToken;
});

Route::prefix('translations')->middleware([$readApiThrottle])->group(base_path('routes/api/translations.php'));

Route::prefix('webhooks')->middleware(['throttle:120,1'])->group(base_path('routes/api/webhooks.php'));

Route::prefix('bootstrap')->middleware([$readApiThrottle])->group(base_path('routes/api/public/bootstrap.php'));

Route::prefix('push-actions')->middleware(['throttle:120,1'])->group(function () {
    Route::post('/reply', [PushActionController::class, 'reply']);
    Route::post('/read', [PushActionController::class, 'read']);
    Route::post('/mute-chat', [PushActionController::class, 'muteChat']);
    Route::post('/answer-call', [PushActionController::class, 'answerCall']);
    Route::post('/decline-call', [PushActionController::class, 'declineCall']);
});

Route::prefix('mobile-auth')->middleware(['throttle:30,1'])->group(function () {
    Route::post('/google', [GoogleNativeAuthController::class, 'issue']);
});

Route::prefix('bootstrap')->middleware(['auth:sanctum', $readApiThrottle])->group(base_path('routes/api/user/bootstrap.php'));

Route::prefix('settings')->middleware(['auth:sanctum', $interactiveApiThrottle])->group(base_path('routes/api/user/account_settings.php'));

Route::prefix('auth')->middleware(['auth:sanctum', $lightApiThrottle])->group(base_path('routes/api/user/auth.php'));

Route::prefix('post/editor')->middleware(['auth:sanctum', $uploadApiThrottle])->group(base_path('routes/api/user/post_editor.php'));

Route::prefix('story/editor')->middleware(['auth:sanctum', $uploadApiThrottle])->group(base_path('routes/api/user/story_editor.php'));

Route::prefix('timeline')->middleware(['auth:sanctum', $readApiThrottle])->group(base_path('routes/api/user/timeline.php'));

Route::prefix('stories')->middleware(['auth:sanctum', $interactiveApiThrottle])->group(base_path('routes/api/user/stories.php'));

Route::prefix('profile')->middleware(['auth:sanctum', $readApiThrottle])->group(base_path('routes/api/user/profile.php'));

Route::prefix('relations')->middleware(['auth:sanctum', $interactiveApiThrottle])->group(base_path('routes/api/user/relations.php'));

Route::prefix('marketplace')->middleware(['auth:sanctum', $readApiThrottle])->group(base_path('routes/api/user/marketplace.php'));

Route::prefix('jobs')->middleware(['auth:sanctum', $readApiThrottle])->group(base_path('routes/api/user/jobs.php'));

Route::prefix('messenger')->middleware(['auth:sanctum', $interactiveApiThrottle])->group(base_path('routes/api/user/messenger.php'));

Route::prefix('admin')->middleware(['auth:sanctum', $lightApiThrottle])->group(base_path('routes/api/user/admin.php'));

Route::prefix('recommendations')->middleware(['auth:sanctum', $readApiThrottle])->group(base_path('routes/api/user/recommend.php'));

Route::prefix('explore')->middleware(['auth:sanctum', $readApiThrottle])->group(base_path('routes/api/user/explore.php'));

Route::prefix('notifications')->middleware(['auth:sanctum', $interactiveApiThrottle])->group(base_path('routes/api/user/notifications.php'));

Route::prefix('autocompletes')->middleware(['auth:sanctum', $readApiThrottle])->group(base_path('routes/api/user/autocompletes.php'));

Route::prefix('translator')->middleware(['auth:sanctum', $interactiveApiThrottle])->group(base_path('routes/api/user/translator.php'));

Route::prefix('feedback')->middleware(['auth:sanctum', $lightApiThrottle])->group(base_path('routes/api/user/feedback.php'));

Route::prefix('bookmarks')->middleware(['auth:sanctum', $interactiveApiThrottle])->group(base_path('routes/api/user/bookmarks.php'));

Route::prefix('wallet')->middleware(['auth:sanctum', $interactiveApiThrottle])->group(base_path('routes/api/user/wallet.php'));

Route::prefix('system')->middleware([$lightApiThrottle])->group(base_path('routes/api/system/master.php'));

Route::prefix('ads')->middleware([$lightApiThrottle])->group(base_path('routes/api/ads/ad.php'));

Route::prefix('tips')->middleware(['auth:sanctum', $interactiveApiThrottle])->group(base_path('routes/api/user/tips.php'));

Route::prefix('pins')->middleware(['auth:sanctum', $interactiveApiThrottle])->group(base_path('routes/api/user/pins.php'));

Route::prefix('ai')->middleware(['auth:sanctum', $interactiveApiThrottle])->group(base_path('routes/api/ai/user.php'));
