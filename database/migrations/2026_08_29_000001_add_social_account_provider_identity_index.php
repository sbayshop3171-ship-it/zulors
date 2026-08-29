<?php

use App\Database\Configs\Table;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $duplicateIdentities = DB::table(Table::SOCIAL_ACCOUNTS)
            ->select(['provider_name', 'provider_id'])
            ->groupBy(['provider_name', 'provider_id'])
            ->havingRaw('COUNT(*) > 1')
            ->get();

        foreach($duplicateIdentities as $identity) {
            $accountIds = DB::table(Table::SOCIAL_ACCOUNTS)
                ->join(Table::USERS, Table::USERS . '.id', '=', Table::SOCIAL_ACCOUNTS . '.user_id')
                ->where(Table::SOCIAL_ACCOUNTS . '.provider_name', $identity->provider_name)
                ->where(Table::SOCIAL_ACCOUNTS . '.provider_id', $identity->provider_id)
                ->orderByRaw("CASE WHEN " . Table::USERS . ".status = 'active' THEN 0 ELSE 1 END")
                ->orderBy(Table::SOCIAL_ACCOUNTS . '.id')
                ->get([Table::SOCIAL_ACCOUNTS . '.id as social_account_id'])
                ->pluck('social_account_id');

            $redundantAccountIds = $accountIds->slice(1)->values();

            if($redundantAccountIds->isNotEmpty()) {
                DB::table(Table::SOCIAL_ACCOUNTS)
                    ->whereIn('id', $redundantAccountIds->all())
                    ->delete();
            }
        }

        Schema::table(Table::SOCIAL_ACCOUNTS, function (Blueprint $table) {
            $table->unique(
                ['provider_name', 'provider_id'],
                'social_accounts_provider_identity_unique'
            );
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table(Table::SOCIAL_ACCOUNTS, function (Blueprint $table) {
            $table->dropUnique('social_accounts_provider_identity_unique');
        });
    }
};
