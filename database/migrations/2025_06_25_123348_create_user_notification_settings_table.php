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
        Schema::create(Table::USER_NOTIFICATION_SETTINGS, function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->foreign('user_id')->references('id')->on(Table::USERS)->onDelete('cascade');

            $table->string('type');

            $table->boolean('direct_messages')->default(false);
            $table->boolean('reactions')->default(false);
            $table->boolean('comments')->default(false);
            $table->boolean('shared_posts')->default(false);
            $table->boolean('followers')->default(false);
            $table->boolean('follow_request')->default(false);
            $table->boolean('mentions')->default(false);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists(Table::USER_NOTIFICATION_SETTINGS);
    }
};
