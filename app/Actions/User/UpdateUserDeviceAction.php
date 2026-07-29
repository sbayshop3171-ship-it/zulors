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

namespace App\Actions\User;

use App\Models\User;
use Jenssegers\Agent\Agent;
use App\Services\World\IpLocationService;

class UpdateUserDeviceAction
{
	private $ipLocationService;
	
	public function __construct()
	{
		$this->ipLocationService = app(IpLocationService::class);
	}

	public function execute(User $user)
	{
		$request = request();
        $userAgent = $request->header('User-Agent');
        $agent = new Agent();
        $agent->setUserAgent($userAgent);
        $userIp = $request->ip();
        $deviceHash = $this->generateDeviceHash($agent);

        $userDevice = $user->devices()->where('device_hash', $deviceHash)->first();

        if(empty($userDevice)) {
            $userLocationData = [];

            if(in_array($userIp, ['127.0.0.1', '::1']) != true) {
                $locationData = $this->ipLocationService->getLocation($userIp);

				if($locationData) {
					$userLocationData = $locationData;
				}
            }

            $userDevice = $user->devices()->create([
                'session_id' => session()->getId(),
                'device_hash' => $deviceHash,
                'platform' => $agent->platform(),
                'platform_version' => $agent->version($agent->platform()),
                'browser' => $agent->browser(),
                'browser_version' => $agent->version($agent->browser()),
                'ip_address' => $userIp,
                'user_agent' => $userAgent,
                'platform_type' => $agent->isMobile() ? 'mobile' : 'desktop',
                'is_location_unknown' => empty($userLocationData),
                'last_online' => now(),
                'country' => data_get($userLocationData, 'country', null),
                'region' => data_get($userLocationData, 'region', null),
                'city' => data_get($userLocationData, 'city', null),
                'timezone' => data_get($userLocationData, 'timezone', null)
            ]);
        }

        elseif ($userDevice->is_terminated == true) {
            auth()->guard('web')->logout();

            $request->session()->invalidate();
            $request->session()->regenerateToken();
            
            $userDevice->delete();
        }

        else{
            $userDevice->update([
                'last_online' => now(),
                'session_id' => session()->getId()
            ]);
        }
	}

    private function generateDeviceHash(Agent $agent) {
        return hash('sha256', join('-', [
            $agent->platform(),
            $agent->version($agent->platform()),
            $agent->browser(),
            $agent->version($agent->browser())
        ]));
    }
}