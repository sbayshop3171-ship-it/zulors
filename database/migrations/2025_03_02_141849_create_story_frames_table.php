<?php

use App\Enums\Story\StoryType;
use App\Database\Configs\Table;
use App\Enums\Story\StoryStatus;
use App\Enums\Story\StoryPrivacy;
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
        Schema::create(Table::STORY_FRAMES, function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('story_id');
            $table->foreign('story_id')->references('id')->on(Table::STORIES)->onDelete('cascade');
            $table->text('content')->nullable();
            $table->string('status')->default(StoryStatus::DRAFT);
            $table->string('type')->default(StoryType::IMAGE);
            $table->string('privacy')->default(StoryPrivacy::ALL);
            $table->integer('views_count')->default(0);
            $table->boolean('is_highlight')->default(false);
            $table->integer('duration_seconds')->default(0);
            $table->json('meta');
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('expires_at')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists(Table::STORY_FRAMES);
    }
};
