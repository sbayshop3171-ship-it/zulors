<?php

use App\Database\Configs\Table;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if(! Schema::hasColumn(Table::CHAT_PARTICIPANTS, 'notifications_muted_until')) {
            Schema::table(Table::CHAT_PARTICIPANTS, function (Blueprint $table) {
                $table->timestamp('notifications_muted_until')->nullable()->after('last_read_at');
                $table->index('notifications_muted_until');
            });
        }
    }

    public function down(): void
    {
        if(Schema::hasColumn(Table::CHAT_PARTICIPANTS, 'notifications_muted_until')) {
            Schema::table(Table::CHAT_PARTICIPANTS, function (Blueprint $table) {
                $table->dropIndex(['notifications_muted_until']);
                $table->dropColumn('notifications_muted_until');
            });
        }
    }
};
