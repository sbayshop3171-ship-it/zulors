<?php

use App\Database\Configs\Table;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create(Table::MUTES, function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('muter_id');
            $table->unsignedBigInteger('muted_id');
            $table->foreign('muter_id')->references('id')->on(Table::USERS)->onDelete('cascade');
            $table->foreign('muted_id')->references('id')->on(Table::USERS)->onDelete('cascade');
            $table->timestamps();
            $table->unique(['muter_id', 'muted_id'], 'unique_mute');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists(Table::MUTES);
    }
};
