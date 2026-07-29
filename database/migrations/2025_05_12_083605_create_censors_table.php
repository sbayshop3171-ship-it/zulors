<?php

use App\Enums\CensorLevel;
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
        Schema::create(Table::CENSORS, function (Blueprint $table) {
            $table->id();
            $table->string('word');
            $table->string('level')->default(CensorLevel::REPLACED);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists(Table::CENSORS);
    }
};
