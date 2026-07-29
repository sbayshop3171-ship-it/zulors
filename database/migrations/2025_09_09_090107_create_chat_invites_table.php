<?php

use App\Database\Configs\Table;
use App\Enums\Chat\ChatInviteStatus;
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
        Schema::create(Table::CHAT_INVITES, function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('chat_id');
            $table->unsignedBigInteger('receiver_id');
            $table->unsignedBigInteger('sender_id');
            $table->string('status')->default(ChatInviteStatus::PENDING);
            $table->foreign('chat_id')->references('id')->on(Table::CHATS)->onDelete('cascade');
            $table->foreign('receiver_id')->references('id')->on(Table::USERS)->onDelete('cascade');
            $table->foreign('sender_id')->references('id')->on(Table::USERS)->onDelete('cascade');
            $table->timestamps();
            $table->unique(['chat_id', 'receiver_id', 'sender_id']);
            $table->timestamp('expires_at')->default(now()->addDays(config('chat.group.invite_expire_days')));
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists(Table::CHAT_INVITES);
    }
};
