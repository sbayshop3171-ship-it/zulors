<?php

use App\Database\Configs\Table;
use App\Enums\Category\CategoryType;
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
        Schema::create(Table::CATEGORIES, function (Blueprint $table) {
            $table->id();
            $table->json('localization');
            $table->unsignedBigInteger('parent_id')->nullable();
            $table->foreign('parent_id')->references('id')->on(Table::CATEGORIES)->onDelete('cascade');
            $table->string('categorizable_type')->default(CategoryType::UNCATEGORIZED);
            $table->integer('depth')->default(1);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists(Table::CATEGORIES);
    }
};
