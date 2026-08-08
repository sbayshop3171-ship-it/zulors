<?php

use App\Database\Configs\Table;
use App\Enums\Call\CallMediaType;
use App\Enums\Call\CallStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create(Table::CALL_SESSIONS, function (Blueprint $table) {
            $table->id();
            $table->uuid('call_uuid')->unique();
            $table->unsignedBigInteger('chat_id');
            $table->unsignedBigInteger('initiator_id');
            $table->unsignedBigInteger('receiver_id');
            $table->string('media_type', 16)->default(CallMediaType::AUDIO->value);
            $table->string('status', 24)->default(CallStatus::RINGING->value);
            $table->string('end_reason', 64)->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('answered_at')->nullable();
            $table->timestamp('connected_at')->nullable();
            $table->timestamp('ended_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();

            $table->foreign('chat_id')->references('id')->on(Table::CHATS)->onDelete('cascade');
            $table->foreign('initiator_id')->references('id')->on(Table::USERS)->onDelete('cascade');
            $table->foreign('receiver_id')->references('id')->on(Table::USERS)->onDelete('cascade');
            $table->index(['status', 'expires_at']);
            $table->index(['initiator_id', 'status']);
            $table->index(['receiver_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists(Table::CALL_SESSIONS);
    }
};
