<?php

use App\Database\Configs\Table;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create(Table::DATA_STATS, function (Blueprint $table) {
            $table->id();
            $table->string('media_type');
            $table->string('disk');
            $table->integer('content_size')->default(0);
            $table->integer('content_items')->default(0);
            $table->timestamps();

            $table->unique(['media_type', 'disk']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists(Table::DATA_STATS);
    }
};
