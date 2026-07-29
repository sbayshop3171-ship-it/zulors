<?php

use App\Database\Configs\Table;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create(Table::USER_SAFETY_SCORES, function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->decimal('trust_score', 8, 4)->default(100);
            $table->decimal('spam_score', 8, 4)->default(0);
            $table->unsignedInteger('post_burst_count')->default(0);
            $table->unsignedInteger('content_reports_count')->default(0);
            $table->timestamp('frozen_until')->nullable();
            $table->timestamp('last_violation_at')->nullable();
            $table->timestamps();

            $table->foreign('user_id')->references('id')->on(Table::USERS)->onDelete('cascade');
            $table->unique('user_id', 'user_safety_scores_user_unique');
            $table->index(['frozen_until', 'spam_score'], 'user_safety_freeze_spam_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists(Table::USER_SAFETY_SCORES);
    }
};
