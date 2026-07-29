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
        Schema::create(Table::NOTIFICATIONS, function (Blueprint $table) {
            $table->uuid('id')->primary();
            
            // The type of notification
            $table->string('type');

            // The user who is receiving the notification
            $table->morphs('notifiable');

            // The data of the notification
            $table->json('data');

            $table->timestamp('read_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists(Table::NOTIFICATIONS);
    }
};
