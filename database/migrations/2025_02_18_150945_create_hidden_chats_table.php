<?php

use App\Enums\Chat\ChatType;
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
        Schema::create(Table::HIDDEN_CHATS, function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('chat_id');
            $table->unsignedBigInteger('user_id');
            $table->foreign('chat_id')->references('id')->on(Table::CHATS)->onDelete('cascade');
            $table->foreign('user_id')->references('id')->on(Table::USERS)->onDelete('cascade');
            $table->string('type')->default(ChatType::DIRECT);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists(Table::HIDDEN_CHATS);
    }
};
