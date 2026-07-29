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

namespace App\Http\Controllers\User\Theme;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;

class ThemeController extends Controller
{
    public function switchTheme(Request $request, string $theme)
    {
        if (in_array($theme, ['dark', 'light', 'system'])) {
            store_theme_preference(
                $theme,
                $request->cookie('theme_runtime', config('brand.default_theme'))
            );
        }
        
        return redirect()->back();
    }
}
