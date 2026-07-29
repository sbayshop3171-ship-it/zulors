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
        Schema::create(Table::ACCOUNT_DELETION_FEEDBACK, function (Blueprint $table) {
            $table->id();
            $table->text('feedback_message');
            $table->string('username');
            $table->string('full_name');
            $table->string('email')->default('');
            $table->string('phone')->default('');
            $table->string('registered_at');
            $table->integer('publications')->default(0);
            $table->integer('followers')->default(0);
            $table->integer('following')->default(0);
            $table->string('ip_address')->default('');
            $table->string('user_agent')->default('');
            $table->timestamp('deleted_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists(Table::ACCOUNT_DELETION_FEEDBACK);
    }
};
