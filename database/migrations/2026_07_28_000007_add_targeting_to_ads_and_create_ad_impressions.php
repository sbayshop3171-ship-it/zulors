<?php

use App\Database\Configs\Table;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table(Table::ADS, function (Blueprint $table) {
            $table->json('target_topics')->nullable()->after('target_url');
        });

        Schema::create(Table::AD_IMPRESSIONS, function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('ad_id');
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('fingerprint', 120);
            $table->unsignedInteger('impressions_count')->default(0);
            $table->unsignedInteger('clicks_count')->default(0);
            $table->timestamp('last_seen_at')->nullable();
            $table->timestamp('last_clicked_at')->nullable();
            $table->timestamps();

            $table->foreign('ad_id')->references('id')->on(Table::ADS)->onDelete('cascade');
            $table->foreign('user_id')->references('id')->on(Table::USERS)->onDelete('set null');
            $table->unique(['ad_id', 'fingerprint'], 'ad_impressions_ad_fingerprint_unique');
            $table->index(['fingerprint', 'impressions_count'], 'ad_impressions_fingerprint_count_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists(Table::AD_IMPRESSIONS);

        Schema::table(Table::ADS, function (Blueprint $table) {
            $table->dropColumn('target_topics');
        });
    }
};
