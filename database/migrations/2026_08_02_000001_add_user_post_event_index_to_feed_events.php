<?php

use App\Database\Configs\Table;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table(Table::FEED_EVENTS, function (Blueprint $table) {
            $table->index(['user_id', 'post_id', 'event_type', 'created_at'], 'feed_events_user_post_type_created_index');
        });
    }

    public function down(): void
    {
        Schema::table(Table::FEED_EVENTS, function (Blueprint $table) {
            $table->dropIndex('feed_events_user_post_type_created_index');
        });
    }
};
