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

namespace App\Http\Middleware;

use Closure;
use Illuminate\Support\Str;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie;
use Symfony\Component\HttpFoundation\Response;

class DeviceIdentifierMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        $deviceId = $request->cookie('device_id');

        if (empty($deviceId)) {

            $deviceId = (string) Str::uuid();

            Cookie::queue('device_id', $deviceId, 60 * 24 * 365);
        }

        return $next($request);
    }
}
