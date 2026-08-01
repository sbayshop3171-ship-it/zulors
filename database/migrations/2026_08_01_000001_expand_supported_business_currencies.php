<?php

use App\Models\Currency;
use Illuminate\Database\Migrations\Migration;
use App\Services\Currency\Fiat\FiatCurrencyService;

return new class extends Migration
{
    public function up(): void
    {
        $currencies = $this->supportedCurrencies();
        $codes = array_column($currencies, 'code');

        foreach ($currencies as $index => $currency) {
            Currency::query()->updateOrCreate([
                'alpha_3_code' => $currency['code'],
            ], [
                'name' => $currency['name'],
                'symbol' => $currency['symbol'],
                'symbol_native' => $currency['symbol'],
                'status' => true,
                'is_default' => $currency['code'] === config('app.default_currency'),
                'order' => $index + 1,
            ]);
        }

        Currency::query()
            ->whereNotIn('alpha_3_code', $codes)
            ->update(['status' => false]);

        FiatCurrencyService::forgetCache();
    }

    public function down(): void
    {
        FiatCurrencyService::forgetCache();
    }

    private function supportedCurrencies(): array
    {
        return [
            ['code' => 'BDT', 'name' => 'Bangladeshi taka', 'symbol' => '৳'],
            ['code' => 'USD', 'name' => 'United States dollar', 'symbol' => '$'],
            ['code' => 'INR', 'name' => 'Indian rupee', 'symbol' => '₹'],
            ['code' => 'EUR', 'name' => 'Euro', 'symbol' => '€'],
            ['code' => 'GBP', 'name' => 'British pound', 'symbol' => '£'],
            ['code' => 'AED', 'name' => 'United Arab Emirates dirham', 'symbol' => 'د.إ'],
            ['code' => 'SAR', 'name' => 'Saudi riyal', 'symbol' => '﷼'],
            ['code' => 'MYR', 'name' => 'Malaysian ringgit', 'symbol' => 'RM'],
            ['code' => 'SGD', 'name' => 'Singapore dollar', 'symbol' => 'S$'],
            ['code' => 'CAD', 'name' => 'Canadian dollar', 'symbol' => 'C$'],
            ['code' => 'AUD', 'name' => 'Australian dollar', 'symbol' => 'A$'],
            ['code' => 'CNY', 'name' => 'Chinese yuan', 'symbol' => '¥'],
            ['code' => 'JPY', 'name' => 'Japanese yen', 'symbol' => '¥'],
            ['code' => 'KRW', 'name' => 'South Korean won', 'symbol' => '₩'],
            ['code' => 'TRY', 'name' => 'Turkish lira', 'symbol' => '₺'],
            ['code' => 'BRL', 'name' => 'Brazilian real', 'symbol' => 'R$'],
            ['code' => 'RUB', 'name' => 'Russian ruble', 'symbol' => '₽'],
        ];
    }
};
