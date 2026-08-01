<?php

use App\Database\Configs\Table;
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
        $hasCode = Schema::hasColumn(Table::EMAIL_CONF, 'code');
        $hasCodeExpiresAt = Schema::hasColumn(Table::EMAIL_CONF, 'code_expires_at');

        if (! $hasCode || ! $hasCodeExpiresAt) {
            Schema::table(Table::EMAIL_CONF, function (Blueprint $table) use ($hasCode, $hasCodeExpiresAt): void {
                if (! $hasCode) {
                    $table->string('code', 4)->nullable()->after('token');
                }

                if (! $hasCodeExpiresAt) {
                    $table->timestamp('code_expires_at')->nullable()->after('code');
                }
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $hasCode = Schema::hasColumn(Table::EMAIL_CONF, 'code');
        $hasCodeExpiresAt = Schema::hasColumn(Table::EMAIL_CONF, 'code_expires_at');

        if ($hasCode || $hasCodeExpiresAt) {
            Schema::table(Table::EMAIL_CONF, function (Blueprint $table) use ($hasCode, $hasCodeExpiresAt): void {
                if ($hasCodeExpiresAt) {
                    $table->dropColumn('code_expires_at');
                }

                if ($hasCode) {
                    $table->dropColumn('code');
                }
            });
        }
    }
};
