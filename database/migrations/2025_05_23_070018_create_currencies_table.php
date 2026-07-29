<?php

use App\Database\Configs\Table;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create(Table::CURRENCIES, function (Blueprint $table) {
            $table->id();
            $table->string('alpha_3_code');
            $table->string('name');
            $table->string('symbol');
            $table->string('symbol_native');
            $table->boolean('status')->default(true);
            $table->integer('usage_count')->default(0);
            $table->boolean('is_default')->default(false);
            $table->integer('order')->default(1);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists(Table::CURRENCIES);
    }
};
