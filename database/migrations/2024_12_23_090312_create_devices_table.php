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
        Schema::create(Table::DEVICES, function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->foreign('user_id')->references('id')->on(Table::USERS)->onDelete('cascade');
            $table->string('device_hash');
            $table->string('session_id');
            $table->string('platform');
            $table->string('platform_version')->nullable();
            $table->string('browser');
            $table->string('browser_version')->nullable();
            $table->string('ip_address');
            $table->string('country')->nullable();
            $table->string('region')->nullable();
            $table->string('city')->nullable();
            $table->string('timezone')->nullable();
            $table->string('user_agent')->nullable();
            $table->string('platform_type')->default('desktop');
            $table->string('last_online')->nullable();
            $table->boolean('is_terminated')->default(false);
            $table->boolean('is_location_unknown')->default(false);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists(Table::DEVICES);
    }
};
