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
use Illuminate\Http\Request;
use App\Enums\User\UserStatus;
use Symfony\Component\HttpFoundation\Response;

class UserStatusMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        if(auth_check()) {
            if(me()->status == UserStatus::ONBOARDING) {
                return redirect()->route('user.onboarding.index', 'profile');
            }

            if(me()->status == UserStatus::BLOCKED) {
                // TODO: Add in future blocked user page with message, reason and contact support button.
                abort(403);
            }

            if(me()->status == UserStatus::SUSPENDED) {
                // TODO: Add in future suspended user page with message, reason and contact support button.
                abort(403);
            }
        }

        return $next($request);
    }
}
