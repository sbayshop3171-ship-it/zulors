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
            $table->string('source_type', 24)->default('creative')->after('user_id')->index();
            $table->unsignedBigInteger('source_post_id')->nullable()->after('source_type')->index();

            $table->foreign('source_post_id')->references('id')->on(Table::POSTS)->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table(Table::ADS, function (Blueprint $table) {
            $table->dropForeign(['source_post_id']);
            $table->dropColumn(['source_type', 'source_post_id']);
        });
    }
};
