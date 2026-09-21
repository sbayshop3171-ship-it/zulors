<?php

use App\Database\Configs\Table;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table(Table::ADS, function (Blueprint $table) {
            $table->text('rejection_reason')->nullable()->after('approval');
        });
    }

    public function down(): void
    {
        Schema::table(Table::ADS, fn(Blueprint $table) => $table->dropColumn('rejection_reason'));
    }
};