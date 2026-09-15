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
        Schema::create(Table::STORY_MUSIC_TRACKS, function (Blueprint $table) {
            $table->id();
            $table->string('slug')->unique();
            $table->string('title');
            $table->string('artist')->nullable();
            $table->string('source');
            $table->text('source_url');
            $table->text('license_url');
            $table->string('license_type');
            $table->string('disk')->default('r2_music');
            $table->text('audio_path');
            $table->text('cover_path')->nullable();
            $table->unsignedInteger('duration_seconds')->nullable();
            $table->string('mood')->nullable()->index();
            $table->string('genre')->nullable()->index();
            $table->string('collection')->default('for_you')->index();
            $table->json('tags')->nullable();
            $table->json('meta')->nullable();
            $table->unsignedInteger('sort_order')->default(0)->index();
            $table->unsignedInteger('usage_count')->default(0);
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();

            $table->index(['is_active', 'collection', 'sort_order']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists(Table::STORY_MUSIC_TRACKS);
    }
};
