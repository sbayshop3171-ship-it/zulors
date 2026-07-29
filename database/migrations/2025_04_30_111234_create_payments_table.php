<?php

use App\Database\Configs\Table;
use App\Enums\Payment\PaymentType;
use App\Enums\Payment\PaymentStatus;
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
        Schema::create(Table::PAYMENTS, function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->foreign('user_id')->references('id')->on(Table::USERS)->onDelete('cascade');
            // The ID of the payment in the payment provider. It can be different based on the provider.
            $table->string('payment_uuid');
            $table->string('reference_id')->nullable();
            $table->string('payment_type')->default(PaymentType::DEPOSIT);
            $table->string('payment_method');
            $table->string('status')->default(PaymentStatus::PENDING);
            $table->string('amount');
            $table->string('currency');
            $table->string('description')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists(Table::PAYMENTS);
    }
};
