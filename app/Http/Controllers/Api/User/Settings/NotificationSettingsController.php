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
use App\Traits\Http\Api\SupportsApiResponses;

class NotificationSettingsController extends Controller
{
    use SupportsApiResponses;
    
    private $me;

    public function __construct() {
        $this->me = me();
    }

    public function getPushSettings()
    {
        $pushSettings = $this->me->resolvePushNotificationSettings();

        return $this->responseSuccess([
            'data' => [
                'direct_messages' => $pushSettings->direct_messages,
                'show_message_preview' => $pushSettings->show_message_preview,
                'reactions' => $pushSettings->reactions,
                'comments' => $pushSettings->comments,
                'shared_posts' => $pushSettings->shared_posts,
                'followers' => $pushSettings->followers,
                'follow_request' => $pushSettings->follow_request,
                'mentions' => $pushSettings->mentions,
            ]
        ]);
    }

    public function getEmailSettings()
    {
        $emailSettings = $this->me->resolveEmailNotificationSettings();

        return $this->responseSuccess([
            'data' => [
                'direct_messages' => $emailSettings->direct_messages,
                'reactions' => $emailSettings->reactions,
                'comments' => $emailSettings->comments,
                'shared_posts' => $emailSettings->shared_posts,
                'followers' => $emailSettings->followers,
                'follow_request' => $emailSettings->follow_request,
                'mentions' => $emailSettings->mentions,
            ]
        ]);
    }

    public function updatePushSettings(Request $request)
    {
        $this->me
            ->resolvePushNotificationSettings()
            ->update($this->fetchNotificationSettingsFromRequest($request));

        return $this->responseSuccess([
            'data' => null
        ]);
    }

    public function updateEmailSettings(Request $request)
    {
        $this->me
            ->resolveEmailNotificationSettings()
            ->update($this->fetchNotificationSettingsFromRequest($request));

        return $this->responseSuccess([
            'data' => null
        ]);
    }

    private function fetchNotificationSettingsFromRequest(Request $request) {
        return [
            'comments' => $request->boolean('comments', false),
            'direct_messages' => $request->boolean('direct_messages', false),
            'show_message_preview' => $request->boolean('show_message_preview', true),
            'follow_request' => $request->boolean('follow_request', false),
            'followers' => $request->boolean('followers', false),
            'mentions' => $request->boolean('mentions', false),
            'reactions' => $request->boolean('reactions', false),
            'shared_posts' => $request->boolean('shared_posts', false)
        ];
    }

    public function updateLoginNotification(Request $request)
    {
        $loginNotification = $request->boolean('login_notification', false);

        $this->me->securitySettings()->update([
            'login_notification' => $loginNotification
        ]);

        return $this->responseSuccess([
            'data' => null
        ]);
    }
}
