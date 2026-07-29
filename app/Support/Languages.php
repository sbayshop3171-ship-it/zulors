<?php

namespace App\Support;

use App\Models\Locale;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Cookie;

class Languages
{
    private $languages;

    private $currentLocale;

    /**
     * Create a new class instance.
     */
    public function __construct()
    {
        $this->languages = Cache::rememberForever('locales', function() {
            return Locale::query()->where('status', true)->get();
        });
        
        $this->currentLocale = app()->getLocale();
    }

    public function refreshCache()
    {
        Cache::forget('locales');
    }

    public function getLanguages()
    {
        $languages = $this->languages->where('status', true)->map(function($lang) {
            if($lang->alpha_2_code === $this->currentLocale) {
                $lang->current = true;
            }

            return $lang;
        });

        return $languages;
    }

    public function getLocaleName(): string
    {
        $locale = $this->languages->first(function($lang) {
            return $lang->alpha_2_code === $this->currentLocale;
        });

        return (empty($locale)) ? __('labels.unknown') : $locale->name;
    }

    public function switchLanguage(string $lang): void
    {
        if($this->languageExists($lang)) {
            $this->setUserLocale($lang);

            Cookie::queue('selected_locale', $lang, now()->addYears(3)->unix());

            session()->put('selected_locale', $lang);
        }
    }

    public function languageExists(string $lang): bool
    {
        return $this->languages->where('status', true)->contains('alpha_2_code', $lang);
    }

    public function getDefaultLanguageAlpha2Code(): string
    {
        $defaultLanguage = $this->languages->where('is_default', true)->first();

        return (empty($defaultLanguage)) ? 'en' : $defaultLanguage->alpha_2_code;
    }

    private function setUserLocale(string $lang): void
    {
        if(auth_check()) {
            me()->update([
                'language' => $lang
            ]);
        }
    }
}
