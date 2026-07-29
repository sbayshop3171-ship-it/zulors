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
        Schema::create(Table::USER_PRIVACY_SETTINGS, function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('user_id');
            $table->foreign('user_id')->references('id')->on(Table::USERS)->onDelete('cascade');

            $table->boolean('email_privacy')->default(false);
            $table->boolean('phone_privacy')->default(false);
            $table->boolean('birthdate_privacy')->default(false);
            $table->boolean('country_privacy')->default(false);
            $table->boolean('city_privacy')->default(false);
            $table->boolean('gender_privacy')->default(false);
            $table->boolean('online_privacy')->default(false);
            $table->boolean('last_seen_privacy')->default(false);
            $table->boolean('search_privacy')->default(false);

            $table->unique('user_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists(Table::USER_PRIVACY_SETTINGS);
    }
};
