<?php

use App\Database\Configs\Table;
use App\Enums\Chat\GroupStatus;
use App\Enums\Chat\GroupPrivacy;
use App\Enums\Chat\GroupVisibility;
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
        Schema::create(Table::GROUPS, function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('chat_id');
            $table->unsignedBigInteger('user_id');
            $table->foreign('chat_id')->references('id')->on(Table::CHATS)->onDelete('cascade');
            $table->foreign('user_id')->references('id')->on(Table::USERS)->onDelete('cascade');
            $table->string('avatar')->nullable();
            $table->string('name')->default('');
            $table->string('description')->default('');
            $table->string('visibility')->default(GroupVisibility::PUBLIC);
            $table->string('privacy')->default(GroupPrivacy::OPEN);
            $table->boolean('verified')->default(false);
            $table->string('status')->default(GroupStatus::ACTIVE);
            $table->timestamp('verified_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists(Table::GROUPS);
    }
};
