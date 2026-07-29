<?php

use App\Database\Configs\Table;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create(Table::USER_INTEREST_SCORES, function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->string('topic', 80);
            $table->decimal('score', 10, 4)->default(0);
            $table->unsignedInteger('events_count')->default(0);
            $table->unsignedInteger('positive_events_count')->default(0);
            $table->unsignedInteger('negative_events_count')->default(0);
            $table->timestamp('last_event_at')->nullable();
            $table->timestamps();

            $table->foreign('user_id')->references('id')->on(Table::USERS)->onDelete('cascade');
            $table->unique(['user_id', 'topic'], 'user_interest_user_topic_unique');
            $table->index('topic', 'user_interest_topic_index');
            $table->index(['user_id', 'score'], 'user_interest_user_score_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists(Table::USER_INTEREST_SCORES);
    }
};
