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

namespace App\Http\Controllers\User\Language;

use App\Http\Controllers\Controller;
use App\Support\Languages;

class LanguageController extends Controller
{
    private $appLanguages;

    public function __construct(Languages $appLanguages) {
        $this->appLanguages = $appLanguages;
    }

    public function switchLanguage(string $lang)
    {
        $this->appLanguages->switchLanguage($lang);
        
        return redirect()->back();
    }
}
