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
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Cookie;
use Symfony\Component\HttpFoundation\Response;

class UserLanguageMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        $locale = config('app.locale');

        if(auth_check()) {
            $locale = me()->language;
        }
        
        else if (Cookie::has('selected_locale')) {
            $locale = Cookie::get('selected_locale');
        }

        else if (session()->has('selected_locale')) {
            $locale = session()->get('selected_locale');
        }

        App::setLocale($locale);

        return $next($request);
    }
}
