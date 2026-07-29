<?php

use App\Database\Configs\Table;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create(Table::POST_VIDEO_METRICS, function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('post_id');
            $table->unsignedBigInteger('media_id')->nullable();
            $table->unsignedInteger('plays_count')->default(0);
            $table->unsignedInteger('completions_count')->default(0);
            $table->unsignedInteger('skips_count')->default(0);
            $table->unsignedInteger('loops_count')->default(0);
            $table->unsignedInteger('rewatches_count')->default(0);
            $table->decimal('watch_time_seconds', 12, 3)->default(0);
            $table->decimal('avg_completion_rate', 8, 4)->default(0);
            $table->decimal('completion_rate', 8, 4)->default(0);
            $table->decimal('skip_rate', 8, 4)->default(0);
            $table->decimal('rewatch_rate', 8, 4)->default(0);
            $table->decimal('intelligence_score', 10, 4)->default(0);
            $table->timestamp('last_event_at')->nullable();
            $table->timestamps();

            $table->foreign('post_id')->references('id')->on(Table::POSTS)->onDelete('cascade');
            $table->foreign('media_id')->references('id')->on(Table::MEDIA)->onDelete('set null');
            $table->unique('post_id', 'post_video_metrics_post_unique');
            $table->index('intelligence_score', 'post_video_metrics_score_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists(Table::POST_VIDEO_METRICS);
    }
};
