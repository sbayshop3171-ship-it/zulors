<?php

use App\Enums\User\UserRole;
use App\Enums\User\UserStatus;
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
        Schema::create(Table::USERS, function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('owner_account_id')->nullable();
            $table->string('first_name')->default('');
            $table->string('status')->default(UserStatus::ONBOARDING);
            $table->string('last_name')->default('');
            $table->string('username')->default('');
            $table->string('caption')->default('');
            $table->string('category')->nullable();
            $table->string('bio')->default('');
            $table->string('country')->nullable();
            $table->string('city')->nullable();
            $table->string('birth_day')->nullable();
            $table->string('birth_month')->nullable();
            $table->string('birth_year')->nullable();
            $table->string('age')->nullable();
            $table->string('gender')->default('male');
            $table->string('email')->default('');
            $table->string('phone')->default('');
            $table->string('website')->default('');
            $table->string('last_active')->default(0);
            $table->string('ip_address')->default('0.0.0.0');
            $table->string('language')->default('en');
            $table->string('avatar')->nullable();
            $table->string('cover')->nullable();
            $table->boolean('verified')->default(false);
            $table->timestamp('verified_at')->nullable();
            $table->json('tips');
    
            $table->timestamp('email_verified_at')->nullable();
            $table->string('password');
            $table->string('role')->default(UserRole::USER->value);
            $table->string('theme')->default('light');
            $table->unsignedBigInteger('publications_count')->default(0);
            $table->unsignedBigInteger('followers_count')->default(0);
            $table->unsignedBigInteger('following_count')->default(0);
            $table->rememberToken();
            $table->softDeletes();
            $table->timestamps();
        });

        Schema::create('password_reset_tokens', function (Blueprint $table) {
            $table->string('email')->primary();
            $table->string('token');
            $table->timestamp('created_at')->nullable();
        });

        Schema::create('sessions', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->foreignId('user_id')->nullable()->index();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->longText('payload');
            $table->integer('last_activity')->index();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists(Table::USERS);
        Schema::dropIfExists('password_reset_tokens');
        Schema::dropIfExists('sessions');
    }
};
