<?php

use App\Database\Configs\Table;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create(Table::USER_PUSH_TOKENS, function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->foreign('user_id')->references('id')->on(Table::USERS)->onDelete('cascade');

            $table->string('provider')->default('fcm');
            $table->string('platform', 32)->default('android');
            $table->text('token');
            $table->string('token_hash', 64)->unique();
            $table->string('device_id', 128)->nullable()->index();
            $table->string('device_name', 120)->nullable();
            $table->string('app_version', 60)->nullable();
            $table->timestamp('last_used_at')->nullable();
            $table->timestamp('revoked_at')->nullable()->index();
            $table->timestamps();

            $table->index(['user_id', 'provider', 'platform', 'revoked_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists(Table::USER_PUSH_TOKENS);
    }
};
