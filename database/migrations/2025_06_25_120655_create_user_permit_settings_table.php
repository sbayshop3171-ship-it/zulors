<?php

use App\Database\Configs\Table;
use App\Enums\User\PrivacyPermit;
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
        Schema::create(Table::USER_PERMIT_SETTINGS, function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->foreign('user_id')->references('id')->on(Table::USERS)->onDelete('cascade');

            $table->string('mentions')->default(PrivacyPermit::ALL);
            $table->string('followers')->default(PrivacyPermit::ALL);
            $table->string('direct_messages')->default(PrivacyPermit::ALL);
            $table->string('story_replies')->default(PrivacyPermit::ALL);
            $table->string('group_invites')->default(PrivacyPermit::ALL);
            $table->string('payment_transfers')->default(PrivacyPermit::ALL);

            $table->unique('user_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists(Table::USER_PERMIT_SETTINGS);
    }
};
