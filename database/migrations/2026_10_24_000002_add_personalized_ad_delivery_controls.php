<?php

use App\Database\Configs\Table;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_ad_preferences', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained(Table::USERS)->cascadeOnDelete();
            $table->boolean('personalized_ads')->default(false);
            $table->timestamp('consented_at')->nullable();
            $table->string('consent_version', 24)->nullable();
            $table->timestamps();
        });

        Schema::create('user_ad_interest_scores', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained(Table::USERS)->cascadeOnDelete();
            $table->string('topic', 80);
            $table->decimal('score', 10, 4)->default(0);
            $table->unsignedInteger('events_count')->default(0);
            $table->timestamp('last_event_at')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'topic'], 'user_ad_interest_user_topic_unique');
            $table->index(['user_id', 'score'], 'user_ad_interest_user_score_index');
        });

        Schema::create('ad_deliveries', function (Blueprint $table) {
            $table->id();
            $table->uuid('delivery_id')->unique();
            $table->foreignId('ad_id')->constrained(Table::ADS)->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained(Table::USERS)->nullOnDelete();
            $table->string('viewer_key', 64);
            $table->string('placement', 24)->default('feed');
            $table->string('selection_lane', 24)->default('relevance');
            $table->string('model_version', 32)->default('rules-v1');
            $table->json('reason_codes')->nullable();
            $table->timestamp('served_at');
            $table->timestamp('viewable_at')->nullable();
            $table->timestamp('cta_clicked_at')->nullable();
            $table->timestamp('converted_at')->nullable();
            $table->timestamp('negative_feedback_at')->nullable();
            $table->decimal('watch_time_seconds', 10, 2)->default(0);
            $table->decimal('completion_rate', 6, 4)->nullable();
            $table->timestamps();

            $table->index(['ad_id', 'viewer_key', 'viewable_at'], 'ad_delivery_viewer_frequency_index');
            $table->index(['ad_id', 'placement', 'viewable_at'], 'ad_delivery_placement_viewable_index');
            $table->index(['user_id', 'served_at'], 'ad_delivery_user_retention_index');
        });

        Schema::table('ad_events', function (Blueprint $table) {
            $table->uuid('delivery_id')->nullable()->after('user_id')->index();
            $table->string('selection_lane', 24)->nullable()->after('placement');
            $table->string('model_version', 32)->nullable()->after('selection_lane');
        });
    }

    public function down(): void
    {
        Schema::table('ad_events', function (Blueprint $table) {
            $table->dropIndex(['delivery_id']);
            $table->dropColumn(['delivery_id', 'selection_lane', 'model_version']);
        });

        Schema::dropIfExists('ad_deliveries');
        Schema::dropIfExists('user_ad_interest_scores');
        Schema::dropIfExists('user_ad_preferences');
    }
};
