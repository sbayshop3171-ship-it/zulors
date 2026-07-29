<?php

namespace Database\Seeders;

use App\Models\Locale;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Schema;

class LocaleSeeder extends Seeder
{
    public function run(): void
    {
        $locales = require database_path('data/locales.php');

        Schema::disableForeignKeyConstraints();

        Locale::query()->truncate();

        foreach ($locales as $locale) {
            Locale::create([
                'alpha_2_code' => $locale['code'],
                'name' => $locale['name'],
                'native_name' => $locale['native_name'],
                'flag_path' => $locale['flag_path'],
                'direction' => $locale['direction'],
                'order' => $locale['order'],
                'is_default' => $locale['is_default'],
            ]);
        }

        Schema::enableForeignKeyConstraints();
    }
}
