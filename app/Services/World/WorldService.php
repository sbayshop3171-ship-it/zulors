<?php

namespace App\Services\World;

use Exception;
use Illuminate\Support\Facades\Log;

class WorldService 
{
    private string $locale = 'en';

    public function __construct()
    {
        $this->locale = app()->getLocale();
    }

    public function setLocale(string $locale): WorldService
    {
        $this->locale = $locale;

        return $this;
    }

    public function countries(): array
    {
        $localPath = var_path("world/countries/{$this->locale}.php");
        
        if(file_exists($localPath)) {
            $countries = require($localPath);
        
            return $countries;
        }
        else{
            Log::error("The [{$this->locale}] countries file is missing.");

            throw new Exception("Required countries [{$this->locale}] configuration file is missing.");
        }
    }

    public function getUserCategories(): array
    {
        $userFields = $this->getUserFields();

        return $userFields['categories'] ?? [];
    }

    public function getCountryKeys(): array
    {
        return array_keys($this->countries());
    }

    public function getCountryName(string $isoCode): string|null
    {
        $countries = $this->countries();
        
        return collect($countries)->first(function($v, $key) use ($isoCode) {
            return $key == $isoCode;
        }, '--Country-Not-Found--');
    }

    private function getUserFields(): array
    {
        $localPath = var_path("world/user_fields/{$this->locale}.php");

        if(file_exists($localPath)) {
            $userFields = require($localPath);
        }

        return $userFields;
    }
}