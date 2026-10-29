<?php

use App\Database\Configs\Table;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table(Table::MESSAGES, function (Blueprint $table): void {
            if (! Schema::hasColumn(Table::MESSAGES, 'client_uid')) {
                $table->string('client_uid', 100)->nullable();
            }

            if (! Schema::hasColumn(Table::MESSAGES, 'client_payload_hash')) {
                $table->char('client_payload_hash', 64)->nullable();
            }

            if (! Schema::hasColumn(Table::MESSAGES, 'forwarded_from')) {
                $table->json('forwarded_from')->nullable();
            }
        });

        if (! Schema::hasIndex(Table::MESSAGES, 'messages_chat_user_client_uid_unique')) {
            Schema::table(Table::MESSAGES, function (Blueprint $table): void {
                $table->unique(
                    ['chat_id', 'user_id', 'client_uid'],
                    'messages_chat_user_client_uid_unique'
                );
            });
        }
    }

    public function down(): void
    {
        // Keep the nullable identity and attribution fields so rolling back the
        // application never destroys message data or retry protection metadata.
    }
};
