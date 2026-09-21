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
            $table->string('objective', 24)->nullable()->after('source_post_id')->index();
            $table->json('placement_flags')->nullable()->after('objective');
            $table->string('cta_type', 32)->nullable()->after('placement_flags');
            $table->string('destination_type', 32)->nullable()->after('cta_type');
            $table->timestamp('start_at')->nullable()->after('destination_type')->index();
            $table->timestamp('end_at')->nullable()->after('start_at')->index();
            $table->unsignedInteger('frequency_cap')->nullable()->after('end_at');
            $table->string('target_category', 120)->nullable()->after('frequency_cap');
        });
    }

    public function down(): void
    {
        Schema::table(Table::ADS, function (Blueprint $table) {
            $table->dropIndex(['objective']);
            $table->dropIndex(['start_at']);
            $table->dropIndex(['end_at']);
            $table->dropColumn([
                'objective',
                'placement_flags',
                'cta_type',
                'destination_type',
                'start_at',
                'end_at',
                'frequency_cap',
                'target_category',
            ]);
        });
    }
};