<?php

use App\Database\Configs\Table;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table(Table::ADS, function (Blueprint $table) {
            if(! Schema::hasColumn(Table::ADS, 'clicks_count')) {
                $table->integer('clicks_count')->default(0)->after('views_count');
            }
        });

        Schema::table(Table::AD_IMPRESSIONS, function (Blueprint $table) {
            if(! Schema::hasColumn(Table::AD_IMPRESSIONS, 'clicks_count')) {
                $table->unsignedInteger('clicks_count')->default(0)->after('impressions_count');
            }

            if(! Schema::hasColumn(Table::AD_IMPRESSIONS, 'last_clicked_at')) {
                $table->timestamp('last_clicked_at')->nullable()->after('last_seen_at');
            }
        });

        DB::table(Table::ADS)
            ->where('views_count', 1)
            ->where('spent_budget', 0)
            ->update(['views_count' => 0]);
    }

    public function down(): void
    {
        Schema::table(Table::AD_IMPRESSIONS, function (Blueprint $table) {
            if(Schema::hasColumn(Table::AD_IMPRESSIONS, 'last_clicked_at')) {
                $table->dropColumn('last_clicked_at');
            }

            if(Schema::hasColumn(Table::AD_IMPRESSIONS, 'clicks_count')) {
                $table->dropColumn('clicks_count');
            }
        });

        Schema::table(Table::ADS, function (Blueprint $table) {
            if(Schema::hasColumn(Table::ADS, 'clicks_count')) {
                $table->dropColumn('clicks_count');
            }
        });
    }
};
