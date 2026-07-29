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
        Schema::create(Table::STORIES, function (Blueprint $table) {
            $table->id();
            $table->uuid('story_uuid');
            $table->unsignedBigInteger('user_id');
            $table->foreign('user_id')->references('id')->on(Table::USERS)->onDelete('cascade');
            $table->timestamp('updated_at')->useCurrent();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists(Table::STORIES);
    }
};
