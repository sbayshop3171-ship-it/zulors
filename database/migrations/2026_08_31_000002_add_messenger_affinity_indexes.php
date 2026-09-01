<?php

use App\Database\Configs\Table;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table(Table::CHATS, function (Blueprint $table) {
            $table->index('chat_id', 'chats_chat_id_index');
            $table->index('last_activity', 'chats_last_activity_index');
        });

        Schema::table(Table::MESSAGES, function (Blueprint $table) {
            $table->index(['chat_id', 'id'], 'messages_chat_id_id_index');
            $table->index(['chat_uuid', 'id'], 'messages_chat_uuid_id_index');
        });

        Schema::table(Table::CHAT_PARTICIPANTS, function (Blueprint $table) {
            $table->index(['user_id', 'chat_id'], 'chat_participants_user_chat_index');
            $table->index(['chat_id', 'user_id'], 'chat_participants_chat_user_index');
        });

        Schema::table(Table::HIDDEN_MESSAGES, function (Blueprint $table) {
            $table->index(['user_id', 'message_id'], 'hidden_messages_user_message_index');
        });

        Schema::table(Table::HIDDEN_CHATS, function (Blueprint $table) {
            $table->index(['user_id', 'chat_id'], 'hidden_chats_user_chat_index');
        });

        Schema::table(Table::ARCHIVED_CHATS, function (Blueprint $table) {
            $table->index(['user_id', 'chat_id'], 'archived_chats_user_chat_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table(Table::ARCHIVED_CHATS, function (Blueprint $table) {
            $table->dropIndex('archived_chats_user_chat_index');
        });

        Schema::table(Table::HIDDEN_CHATS, function (Blueprint $table) {
            $table->dropIndex('hidden_chats_user_chat_index');
        });

        Schema::table(Table::HIDDEN_MESSAGES, function (Blueprint $table) {
            $table->dropIndex('hidden_messages_user_message_index');
        });

        Schema::table(Table::CHAT_PARTICIPANTS, function (Blueprint $table) {
            $table->dropIndex('chat_participants_chat_user_index');
            $table->dropIndex('chat_participants_user_chat_index');
        });

        Schema::table(Table::MESSAGES, function (Blueprint $table) {
            $table->dropIndex('messages_chat_uuid_id_index');
            $table->dropIndex('messages_chat_id_id_index');
        });

        Schema::table(Table::CHATS, function (Blueprint $table) {
            $table->dropIndex('chats_last_activity_index');
            $table->dropIndex('chats_chat_id_index');
        });
    }
};
