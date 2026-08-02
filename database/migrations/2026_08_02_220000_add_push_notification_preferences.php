<?php

use App\Database\Configs\Table;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if(! Schema::hasColumn(Table::USER_NOTIFICATION_SETTINGS, 'show_message_preview')) {
            Schema::table(Table::USER_NOTIFICATION_SETTINGS, function (Blueprint $table) {
                $table->boolean('show_message_preview')->default(true)->after('direct_messages');
            });
        }

        DB::table(Table::USER_NOTIFICATION_SETTINGS)
            ->where('type', 'push')
            ->update([
                'direct_messages' => true,
                'show_message_preview' => true,
                'reactions' => true,
                'comments' => true,
                'shared_posts' => true,
                'followers' => true,
                'follow_request' => true,
                'mentions' => true,
            ]);
    }

    public function down(): void
    {
        if(Schema::hasColumn(Table::USER_NOTIFICATION_SETTINGS, 'show_message_preview')) {
            Schema::table(Table::USER_NOTIFICATION_SETTINGS, function (Blueprint $table) {
                $table->dropColumn('show_message_preview');
            });
        }
    }
};
