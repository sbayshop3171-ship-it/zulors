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
use Illuminate\Validation\Rule;
use App\Http\Controllers\Controller;
use App\Services\World\WorldService;
use App\Traits\Http\Api\SupportsApiResponses;

class PersonalInfoSettingsController extends Controller
{
    use SupportsApiResponses;
    
    private $me;

    public function __construct() {
        $this->me = me();
    }

    public function getCountrySettings(WorldService $worldService)
    {
        return $this->responseSuccess([
           'data' => [
                'country' => $this->me->country,
                'privacy_settings' => [
                    'country_privacy' => $this->me->privacySettings->country_privacy
                ],
                'countries' => collect($worldService->countries())->map(function($value, $key) {
                    return [
                        'value' => $key,
                        'label' => $value
                    ];
                })->values()->toArray()
            ] 
        ]);
    }

    public function getPersonalInfoSettings(Request $request)
    {
        return $this->responseSuccess([
           'data' => [
                'country' => empty($this->me->country) ? null : $this->me->countryName,
                'birth_date' => $this->me->birthdate,
                'city' => (empty($this->me->city)) ? null : $this->me->city
            ] 
        ]);
    }

    public function getBirthdateSettings()
    {
        return $this->responseSuccess([
           'data' => [
                'birth_date' => [
                    'day' => $this->me->birth_day,
                    'month' => $this->me->birth_month,
                    'year' => $this->me->birth_year
                ],
                'privacy_settings' => [
                    'birthdate_privacy' => $this->me->privacySettings->birthdate_privacy
                ],
                'calendar' => [
                    'days' => collect(month_days($this->me->birth_month, $this->me->birth_year))->map(function($day) {
                        return [
                            'value' => $day['key'],
                            'label' => $day['value']
                        ];
                    }),
                    'years' => collect(birth_years())->map(function($year) {
                        return [
                            'value' => $year['key'],
                            'label' => $year['value']
                        ];
                    }),
                    'months' => collect(year_months())->map(function($month) {
                        return [
                            'value' => $month['key'],
                            'label' => $month['value']
                        ];
                    })
                ]
           ]
        ]);
    }

    public function updateBirthdateSettings(Request $request)
    {
        $birthDate = $request->get('birth_date');
        $birthDatePrivacy = $request->boolean('birthdate_privacy', false);

        $request->validate([
            'birth_date.day' => ['required', 'numeric', 'min:1', 'max:31'],
            'birth_date.month' => ['required', 'numeric', Rule::in(array_column(year_months(), 'key'))],
            'birth_date.year' => ['required', 'numeric', Rule::in(array_column(birth_years(), 'key'))]
        ]);

        $this->me->update([
            'birth_day' => $birthDate['day'],
            'birth_month' => $birthDate['month'],
            'birth_year' => $birthDate['year'],
        ]);

        $this->me->privacySettings()->update([
            'birthdate_privacy' => $birthDatePrivacy
        ]);

        return $this->responseSuccess([
            'data' => null
        ]);
    }

    public function updateCountrySettings(Request $request, WorldService $worldService)
    {
        $country = $request->get('country');
        $countryPrivacy = $request->boolean('country_privacy', false);

        $request->validate([
            'country' => ['required', 'string', 'size:2', 'uppercase', Rule::in($worldService->getCountryKeys())]
        ]);

        $this->me->update([
            'country' => $country
        ]);

        $this->me->privacySettings()->update([
            'country_privacy' => $countryPrivacy
        ]);

        return $this->responseSuccess([
            'data' => null
        ]);
    }

    public function getCitySettings() {
        return $this->responseSuccess([
           'data' => [
                'city' => (string) $this->me->city,
                'privacy_settings' => [
                    'city_privacy' => $this->me->privacySettings->city_privacy
                ]
            ] 
        ]);
    }

    public function updateCitySettings(Request $request)
    {
        $city = $request->get('city');
        $cityPrivacy = $request->boolean('city_privacy', false);

        $request->validate([
            'city' => ['nullable', 'string', 'max:255']
        ]); 

        $this->me->update([
            'city' => $city
        ]);

        $this->me->privacySettings()->update([
            'city_privacy' => $cityPrivacy
        ]);

        return $this->responseSuccess([
            'data' => null
        ]);
    }
}
