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
        $hasPlacement = Schema::hasColumn(Table::AD_IMPRESSIONS, 'placement');
        $hasDevice = Schema::hasColumn(Table::AD_IMPRESSIONS, 'device');

        if (! $hasPlacement || ! $hasDevice) {
            Schema::table(Table::AD_IMPRESSIONS, function (Blueprint $table) use ($hasPlacement, $hasDevice) {
                if (! $hasPlacement) {
                    $table->string('placement', 24)->default('sidebar')->after('fingerprint');
                }

                if (! $hasDevice) {
                    $table->string('device', 16)->nullable()->after('placement');
                }
            });
        }

        if ($this->hasIndex('ad_impressions_ad_fingerprint_unique')) {
            Schema::table(Table::AD_IMPRESSIONS, function (Blueprint $table) {
                $table->dropUnique('ad_impressions_ad_fingerprint_unique');
            });
        }

        if (! $this->hasIndex('ad_impressions_ad_fingerprint_placement_unique')) {
            Schema::table(Table::AD_IMPRESSIONS, function (Blueprint $table) {
                $table->unique(['ad_id', 'fingerprint', 'placement'], 'ad_impressions_ad_fingerprint_placement_unique');
            });
        }
    }

    public function down(): void
    {
        if ($this->hasIndex('ad_impressions_ad_fingerprint_placement_unique')) {
            Schema::table(Table::AD_IMPRESSIONS, function (Blueprint $table) {
                $table->dropUnique('ad_impressions_ad_fingerprint_placement_unique');
            });
        }

        if (! $this->hasIndex('ad_impressions_ad_fingerprint_unique')) {
            Schema::table(Table::AD_IMPRESSIONS, function (Blueprint $table) {
                $table->unique(['ad_id', 'fingerprint'], 'ad_impressions_ad_fingerprint_unique');
            });
        }

        $dropColumns = array_values(array_filter([
            Schema::hasColumn(Table::AD_IMPRESSIONS, 'placement') ? 'placement' : null,
            Schema::hasColumn(Table::AD_IMPRESSIONS, 'device') ? 'device' : null,
        ]));

        if ($dropColumns) {
            Schema::table(Table::AD_IMPRESSIONS, fn (Blueprint $table) => $table->dropColumn($dropColumns));
        }
    }

    private function hasIndex(string $indexName): bool
    {
        if (DB::getDriverName() === 'sqlite') {
            return collect(DB::select('PRAGMA index_list('.Table::AD_IMPRESSIONS.')'))
                ->contains(fn (object $index) => ($index->name ?? null) === $indexName);
        }

        return collect(DB::select('SHOW INDEX FROM '.Table::AD_IMPRESSIONS.' WHERE Key_name = ?', [$indexName]))->isNotEmpty();
    }
};
