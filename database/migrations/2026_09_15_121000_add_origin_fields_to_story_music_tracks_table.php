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
            $table->unsignedBigInteger('user_id')->nullable()->after('id')->index();
            $table->unsignedBigInteger('source_media_id')->nullable()->after('user_id')->index();
            $table->string('source_media_type')->nullable()->after('source_media_id')->index();
            $table->string('review_status')->default('approved')->after('source_media_type')->index();
            $table->timestamp('published_at')->nullable()->after('is_active')->index();

            $table->index(['source', 'source_media_id']);
            $table->index(['is_active', 'review_status', 'collection']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table(Table::STORY_MUSIC_TRACKS, function (Blueprint $table) {
            $table->dropIndex(['source', 'source_media_id']);
            $table->dropIndex(['is_active', 'review_status', 'collection']);
            $table->dropIndex(['user_id']);
            $table->dropIndex(['source_media_id']);
            $table->dropIndex(['source_media_type']);
            $table->dropIndex(['review_status']);
            $table->dropIndex(['published_at']);
            $table->dropColumn([
                'user_id',
                'source_media_id',
                'source_media_type',
                'review_status',
                'published_at',
            ]);
        });
    }
};
