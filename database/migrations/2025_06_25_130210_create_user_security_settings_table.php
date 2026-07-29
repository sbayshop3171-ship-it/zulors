<?php

use App\Enums\F2AType;
use App\Database\Configs\Table;
use App\Enums\NotificationType;
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
        Schema::create(Table::USER_SECURITY_SETTINGS, function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->foreign('user_id')->references('id')->on(Table::USERS)->onDelete('cascade');

            $table->boolean('2fa')->default(false);
            $table->string('2fa_type')->default(F2AType::EMAIL);

            $table->boolean('login_notification')->default(false);
            $table->string('login_notification_type')->default(NotificationType::EMAIL);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists(Table::USER_SECURITY_SETTINGS);
    }
};
