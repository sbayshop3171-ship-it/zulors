<?php

use App\Database\Configs\Table;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create(Table::AD_REWARD_ACCOUNTS, function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->string('period_month', 7);
            $table->decimal('monthly_amount', 10, 2)->default(0);
            $table->decimal('available_amount', 10, 2)->default(0);
            $table->decimal('used_amount', 10, 2)->default(0);
            $table->string('status', 24)->default('active');
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();

            $table->foreign('user_id')->references('id')->on(Table::USERS)->onDelete('cascade');
            $table->unique(['user_id', 'period_month'], 'ad_reward_accounts_user_period_unique');
            $table->index(['period_month', 'status'], 'ad_reward_accounts_period_status_index');
        });

        Schema::create(Table::AD_REWARD_TRANSACTIONS, function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('ad_reward_account_id')->nullable();
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('ad_id')->nullable();
            $table->string('period_month', 7);
            $table->decimal('amount', 10, 2)->default(0);
            $table->string('transaction_type', 32);
            $table->string('direction', 16);
            $table->string('status', 24)->default('completed');
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->foreign('ad_reward_account_id')->references('id')->on(Table::AD_REWARD_ACCOUNTS)->onDelete('set null');
            $table->foreign('user_id')->references('id')->on(Table::USERS)->onDelete('cascade');
            $table->foreign('ad_id')->references('id')->on(Table::ADS)->onDelete('set null');
            $table->index(['user_id', 'period_month'], 'ad_reward_transactions_user_period_index');
            $table->index(['ad_id', 'transaction_type'], 'ad_reward_transactions_ad_type_index');
        });

        Schema::table(Table::ADS, function (Blueprint $table) {
            $table->json('funding_metadata')->nullable()->after('target_topics');
            $table->string('pause_reason', 80)->nullable()->after('last_charge_at');
        });
    }

    public function down(): void
    {
        Schema::table(Table::ADS, function (Blueprint $table) {
            $table->dropColumn(['funding_metadata', 'pause_reason']);
        });

        Schema::dropIfExists(Table::AD_REWARD_TRANSACTIONS);
        Schema::dropIfExists(Table::AD_REWARD_ACCOUNTS);
    }
};
