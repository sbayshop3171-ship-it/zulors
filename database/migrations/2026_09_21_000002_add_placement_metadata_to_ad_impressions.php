<?php

use App\Database\Configs\Table;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table(Table::AD_IMPRESSIONS, function (Blueprint $table) {
            $table->string('placement', 24)->default('sidebar')->after('fingerprint');
            $table->string('device', 16)->nullable()->after('placement');
        });

        Schema::table(Table::AD_IMPRESSIONS, function (Blueprint $table) {
            $table->dropUnique('ad_impressions_ad_fingerprint_unique');
            $table->unique(['ad_id', 'fingerprint', 'placement'], 'ad_impressions_ad_fingerprint_placement_unique');
        });
    }

    public function down(): void
    {
        Schema::table(Table::AD_IMPRESSIONS, function (Blueprint $table) {
            $table->dropUnique('ad_impressions_ad_fingerprint_placement_unique');
            $table->unique(['ad_id', 'fingerprint'], 'ad_impressions_ad_fingerprint_unique');
            $table->dropColumn(['placement', 'device']);
        });
    }
};