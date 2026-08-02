<?php

use App\Database\Configs\Table;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table(Table::POSTS, function (Blueprint $table) {
            $table->index(['type', 'status', 'created_at', 'id'], 'posts_reels_type_status_created_id_index');
        });
    }

    public function down(): void
    {
        Schema::table(Table::POSTS, function (Blueprint $table) {
            $table->dropIndex('posts_reels_type_status_created_id_index');
        });
    }
};
