<?php

use App\Database\Configs\Table;
use App\Enums\Call\CallStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create(Table::CALL_PARTICIPANTS, function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('call_session_id');
            $table->unsignedBigInteger('user_id');
            $table->string('role', 16);
            $table->string('status', 24)->default(CallStatus::RINGING->value);
            $table->timestamp('joined_at')->nullable();
            $table->timestamp('left_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->foreign('call_session_id')->references('id')->on(Table::CALL_SESSIONS)->onDelete('cascade');
            $table->foreign('user_id')->references('id')->on(Table::USERS)->onDelete('cascade');
            $table->unique(['call_session_id', 'user_id']);
            $table->index(['user_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists(Table::CALL_PARTICIPANTS);
    }
};
