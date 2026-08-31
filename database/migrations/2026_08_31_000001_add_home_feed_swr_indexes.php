<?php

use App\Database\Configs\Table;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table(Table::FOLLOWS, function (Blueprint $table) {
            $table->index(['follower_id', 'status', 'following_id'], 'follows_follower_status_following_index');
        });

        Schema::table(Table::BLOCKS, function (Blueprint $table) {
            $table->index(['blocked_id', 'blocker_id'], 'blocks_blocked_blocker_index');
        });
    }

    public function down(): void
    {
        Schema::table(Table::BLOCKS, function (Blueprint $table) {
            $table->dropIndex('blocks_blocked_blocker_index');
        });

        Schema::table(Table::FOLLOWS, function (Blueprint $table) {
            $table->dropIndex('follows_follower_status_following_index');
        });
    }
};
