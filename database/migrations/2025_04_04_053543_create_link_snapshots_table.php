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
        Schema::create(Table::LINK_SNAPSHOTS, function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('linkable_id');
            $table->string('linkable_type');
            
            $table->string('url');
            $table->string('domain')->nullable();
            $table->string('title')->nullable();
            $table->string('description')->nullable();
            $table->json('metadata');
            $table->timestamp('expires_at')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists(Table::LINK_SNAPSHOTS);
    }
};
