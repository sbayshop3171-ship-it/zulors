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
            $table->text('primary_text')->nullable()->after('content');
            $table->string('headline', 180)->nullable()->after('title');
            $table->string('creative_version', 32)->nullable()->after('headline');
        });
    }

    public function down(): void
    {
        Schema::table(Table::ADS, function (Blueprint $table) {
            $table->dropColumn(['primary_text', 'headline', 'creative_version']);
        });
    }
};
