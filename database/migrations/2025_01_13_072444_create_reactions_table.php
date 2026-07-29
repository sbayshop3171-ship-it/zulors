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
        Schema::create(Table::REACTIONS, function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('reactable_id');
            $table->string('reactable_type');
            $table->integer('reactions_count')->default(0);
            $table->string('unified_id');
            $table->string('native_symbol')->nullable();
            $table->json('users');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists(Table::REACTIONS);
    }
};
