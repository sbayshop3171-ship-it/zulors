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

use App\Support\Languages;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Traits\Http\Api\SupportsApiResponses;
use App\Http\Resources\User\Language\LanguageResource;

class LanguageSettingsController extends Controller
{
    use SupportsApiResponses;

    public $availableLanguages;

    public function __construct(Languages $availableLanguages) {
        $this->availableLanguages = $availableLanguages;
    }

    public function getLanguages()
    {
        return $this->responseSuccess([
            'data' => $this->availableLanguages->getLanguages()->map(function($localeItem) {
                return LanguageResource::make($localeItem);
            })
        ]);
    }

    public function switchLanguage(Request $request)
    {
        $lang = $request->input('language', 'en');

        $this->availableLanguages->switchLanguage($lang);

        return $this->responseSuccess([
            'data' => null
        ]);
    }
}
