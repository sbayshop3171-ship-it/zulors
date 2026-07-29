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
        Schema::create(Table::MESSAGES, function (Blueprint $table) {
            $table->id();
            $table->string('chat_uuid');
            $table->unsignedBigInteger('chat_id');
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('participant_id');
            $table->unsignedBigInteger('parent_id')->nullable();
            $table->foreign('chat_id')->references('id')->on(Table::CHATS)->onDelete('cascade');
            $table->foreign('user_id')->references('id')->on(Table::USERS)->onDelete('cascade');
            $table->foreign('parent_id')->references('id')->on(Table::MESSAGES)->onDelete('cascade');
            $table->foreign('participant_id')->references('id')->on(Table::CHAT_PARTICIPANTS)->onDelete('cascade');
            $table->text('content')->nullable();
            $table->boolean('is_deleted')->default(false);
            $table->string('text_language')->default('');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists(Table::MESSAGES);
    }
};
