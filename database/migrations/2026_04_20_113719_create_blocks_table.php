<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use App\Database\Configs\Table;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create(Table::BLOCKS, function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('blocker_id');
            $table->unsignedBigInteger('blocked_id');
            $table->foreign('blocker_id')->references('id')->on(Table::USERS)->onDelete('cascade');
            $table->foreign('blocked_id')->references('id')->on(Table::USERS)->onDelete('cascade');
            $table->timestamps();
            $table->unique(['blocker_id', 'blocked_id'], 'unique_block');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists(Table::BLOCKS);
    }
};
