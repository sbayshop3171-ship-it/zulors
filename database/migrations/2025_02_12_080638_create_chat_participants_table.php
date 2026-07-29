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
        Schema::create(Table::CHAT_PARTICIPANTS, function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('chat_id');
            $table->unsignedBigInteger('user_id');
            $table->foreign('chat_id')->references('id')->on(Table::CHATS)->onDelete('cascade');
            $table->foreign('user_id')->references('id')->on(Table::USERS)->onDelete('cascade');
            $table->integer('last_read_message_id')->default(0);
            $table->json('metadata');
            $table->timestamp('last_read_at')->nullable();
            $table->timestamp('joined_at')->useCurrent();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists(Table::CHAT_PARTICIPANTS);
    }
};
