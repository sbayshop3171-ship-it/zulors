<?php

namespace Database\Seeders;

use App\Models\Currency;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Schema;

class CurrencySeeder extends Seeder
{
    public function run(): void
    {
        $currencies = require database_path('data/currencies.php');

        Schema::disableForeignKeyConstraints();

        Currency::query()->truncate();

        foreach ($currencies as $currency) {    
            Currency::create([
                'alpha_3_code' => $currency['code'],
                'name' => $currency['name'],
                'symbol' => $currency['symbol'],
                'symbol_native' => $currency['symbol'],
                'status' => true
            ]);
        }

        Schema::enableForeignKeyConstraints();
    }
}
