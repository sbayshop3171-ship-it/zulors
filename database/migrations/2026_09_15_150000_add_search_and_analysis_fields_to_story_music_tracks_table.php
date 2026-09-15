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
        Schema::table(Table::STORY_MUSIC_TRACKS, function (Blueprint $table) {
            $table->unsignedTinyInteger('audio_quality_score')->nullable()->after('duration_seconds')->index();
            $table->text('search_text')->nullable()->after('tags');
            $table->string('recognition_status')->nullable()->after('review_status')->index();
            $table->string('recognition_provider')->nullable()->after('recognition_status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table(Table::STORY_MUSIC_TRACKS, function (Blueprint $table) {
            $table->dropIndex(['audio_quality_score']);
            $table->dropIndex(['recognition_status']);
            $table->dropColumn([
                'audio_quality_score',
                'search_text',
                'recognition_status',
                'recognition_provider',
            ]);
        });
    }
};
