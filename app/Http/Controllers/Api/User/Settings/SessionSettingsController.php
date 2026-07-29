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

use Carbon\Carbon;
use Illuminate\Support\Str;
use App\Http\Controllers\Controller;
use App\Traits\Http\Api\SupportsApiResponses;
use App\Actions\User\TerminateUserSessionsAction;

class SessionSettingsController extends Controller
{
    use SupportsApiResponses;

    private $me;

    public function __construct() {
        $this->me = me();
    }
    
    public function getSessions()
    {
        $sessions = $this->me->devices()->where('is_terminated', false)->get();
        $userSessionId = session()->getId();

        return $this->responseSuccess([
            'data' => [
                'sessions' => collect($sessions)->map(function ($session) use ($userSessionId) {
                    return [
                        'is_current' => ($session->session_id == $userSessionId),
                        'ip_address' => $session->ip_address,
                        'browser' => [
                            'name' => $session->browser,
                            'version' => Str::before($session->browser_version, '.')
                        ],
                        'os' => [
                            'name' => $session->platform,
                            'version' => $session->platform_version
                        ],
                        'location' => transform($session, function($session) {
                            if($session->is_location_unknown) {
                                return null;
                            }

                            else if(empty($session->country)) {
                                return null;
                            }

                            return [
                                'country' => $session->country,
                                'city' => empty($session->city) ? __('labels.unknown') : $session->city,
                                'region' => empty($session->region) ? __('labels.unknown') : $session->region,
                                'timezone' => empty($session->timezone) ? __('labels.unknown') : $session->timezone,
                            ];
                        }),
                        'is_desktop' => ($session->platform_type == 'desktop'),
                        'last_online' =>  Carbon::parse($session->last_online)->locale(app()->getLocale())->diffForHumans(),
                        'is_online' => ($session->last_online > now()->subMinutes(config('user.online_interval_in_minutes')))
                    ];
                })
            ]
        ]);
    }

    public function terminateOtherSessions()
    {
        (new TerminateUserSessionsAction())->execute();

        return $this->responseSuccess([
            'data' => null
        ]);
    }
}
