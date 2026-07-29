<?php

use App\Database\Configs\Table;
use App\Enums\Wallet\CashoutStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('cashouts', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->string('request_code')->unique();
            $table->foreign('user_id')->references('id')->on(Table::USERS)->onDelete('cascade');
            $table->decimal('amount', 10, 2)->default(0);
            $table->decimal('commission_percentage', 10, 2)->default(0);
            $table->decimal('to_pay', 10, 2)->default(0);
            $table->string('currency')->default(config('app.default_currency'));
            $table->string('payment_method');
            $table->tinyText('credentials');
            $table->tinyText('reviewer_notes')->nullable();
            $table->string('status')->default(CashoutStatus::PENDING);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cashouts');
    }
};
