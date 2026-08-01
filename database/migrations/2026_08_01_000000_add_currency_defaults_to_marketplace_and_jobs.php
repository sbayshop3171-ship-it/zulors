<?php

use App\Database\Configs\Table;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $currency = $this->defaultCurrency();

        DB::table(Table::PRODUCTS)->where('currency', '')->update(['currency' => $currency]);
        DB::table(Table::JOB_LISTINGS)->where('currency', '')->update(['currency' => $currency]);

        if (! in_array(DB::connection()->getDriverName(), ['mysql', 'mariadb'])) {
            return;
        }

        $quotedCurrency = DB::getPdo()->quote($currency);

        DB::statement("ALTER TABLE `products` MODIFY `currency` VARCHAR(255) NOT NULL DEFAULT {$quotedCurrency}");
        DB::statement("ALTER TABLE `job_listings` MODIFY `currency` VARCHAR(255) NOT NULL DEFAULT {$quotedCurrency}");
    }

    public function down(): void
    {
        if (! in_array(DB::connection()->getDriverName(), ['mysql', 'mariadb'])) {
            return;
        }

        DB::statement("ALTER TABLE `products` MODIFY `currency` VARCHAR(255) NOT NULL");
        DB::statement("ALTER TABLE `job_listings` MODIFY `currency` VARCHAR(255) NOT NULL");
    }

    private function defaultCurrency(): string
    {
        return config('app.default_currency') ?: 'USD';
    }
};
