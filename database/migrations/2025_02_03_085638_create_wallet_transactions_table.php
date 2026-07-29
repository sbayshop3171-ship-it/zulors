<?php

use App\Database\Configs\Table;
use App\Enums\Wallet\TransactionType;
use Illuminate\Support\Facades\Schema;
use App\Enums\Wallet\TransactionStatus;
use Illuminate\Database\Schema\Blueprint;
use App\Enums\Wallet\TransactionDirection;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create(Table::WALLET_TRANSACTIONS, function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('wallet_id');
            $table->foreign('wallet_id')->references('id')->on(Table::WALLETS)->onDelete('cascade');
            $table->decimal('amount', 10, 2)->default(0);
            $table->decimal('commission', 10, 2)->default(0);
            $table->string('currency')->default(config('app.default_currency'));
            $table->string('transaction_type')->default(TransactionType::DEPOSIT);
            $table->boolean('is_internal')->default(false);
            $table->string('direction')->default(TransactionDirection::INCOMING);
            $table->string('status')->default(TransactionStatus::PENDING);
            $table->json('metadata');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists(Table::WALLET_TRANSACTIONS);
    }
};
