<?php

use App\Database\Configs\Table;
use App\Enums\Category\CategoryStatus;
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
        Schema::table(Table::CATEGORIES, function (Blueprint $table) {
            $table->string('status')->default(CategoryStatus::ACTIVE);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table(Table::CATEGORIES, function (Blueprint $table) {
            $table->dropColumn('status');
        });
    }
};
