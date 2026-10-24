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
            $table->foreignId('reels_media_id')
                ->nullable()
                ->after('source_post_id')
                ->constrained(Table::MEDIA)
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table(Table::ADS, function (Blueprint $table) {
            $table->dropConstrainedForeignId('reels_media_id');
        });
    }
};
