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
        Schema::create(Table::STORY_VIEWS, function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('story_frame_id');
            $table->unsignedBigInteger('user_id');
            $table->foreign('story_frame_id')->references('id')->on(Table::STORY_FRAMES)->onDelete('cascade');
            $table->foreign('user_id')->references('id')->on(Table::USERS)->onDelete('cascade');
            $table->timestamp('viewed_at')->useCurrent();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists(Table::STORY_VIEWS);
    }
};
