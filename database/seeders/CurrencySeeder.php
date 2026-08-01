<?php

namespace Database\Seeders;

use App\Models\Currency;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;

class CurrencySeeder extends Seeder
{
    public function run(): void
    {
        $currencies = require database_path('data/currencies.php');

        Schema::disableForeignKeyConstraints();

        Currency::query()->truncate();

        foreach ($currencies as $index => $currency) {
            Currency::create([
                'alpha_3_code' => $currency['code'],
                'name' => $currency['name'],
                'symbol' => $currency['symbol'],
                'symbol_native' => $currency['symbol'],
                'status' => $currency['status'] ?? true,
                'is_default' => $currency['code'] === config('app.default_currency'),
                'order' => $currency['order'] ?? ($index + 1),
            ]);
        }

        Cache::forget('world_currencies');

        Schema::enableForeignKeyConstraints();
    }
}
