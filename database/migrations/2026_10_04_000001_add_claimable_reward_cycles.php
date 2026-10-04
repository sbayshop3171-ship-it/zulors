<?php

use App\Database\Configs\Table;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table(Table::AD_REWARD_ACCOUNTS, function (Blueprint $table) {
            $table->string('cycle_key', 10)->nullable()->after('period_month');
            $table->timestamp('claimed_at')->nullable()->after('expires_at');
            $table->index(['user_id', 'cycle_key'], 'ad_reward_accounts_user_cycle_index');
        });
    }

    public function down(): void
    {
        Schema::table(Table::AD_REWARD_ACCOUNTS, function (Blueprint $table) {
            $table->dropIndex('ad_reward_accounts_user_cycle_index');
            $table->dropColumn(['cycle_key', 'claimed_at']);
        });
    }
};
