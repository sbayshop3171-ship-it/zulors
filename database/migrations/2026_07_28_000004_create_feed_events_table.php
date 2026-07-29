<?php

use App\Database\Configs\Table;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create(Table::FEED_EVENTS, function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->unsignedBigInteger('post_id')->nullable();
            $table->unsignedBigInteger('media_id')->nullable();
            $table->string('event_type', 48);
            $table->decimal('watch_time_seconds', 10, 3)->default(0);
            $table->decimal('duration_seconds', 10, 3)->default(0);
            $table->decimal('completion_rate', 8, 4)->default(0);
            $table->json('metadata')->nullable();
            $table->string('session_id', 80)->nullable();
            $table->timestamps();

            $table->foreign('user_id')->references('id')->on(Table::USERS)->onDelete('set null');
            $table->foreign('post_id')->references('id')->on(Table::POSTS)->onDelete('cascade');
            $table->foreign('media_id')->references('id')->on(Table::MEDIA)->onDelete('set null');
            $table->index(['user_id', 'event_type', 'created_at'], 'feed_events_user_type_created_index');
            $table->index(['post_id', 'event_type', 'created_at'], 'feed_events_post_type_created_index');
            $table->index(['media_id', 'event_type'], 'feed_events_media_type_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists(Table::FEED_EVENTS);
    }
};
