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
            $table->index(['status', 'created_at', 'id'], 'posts_feed_status_created_id_index');
            $table->index(['user_id', 'status', 'created_at'], 'posts_feed_user_status_created_index');
        });

        Schema::table(Table::COMMENTS, function (Blueprint $table) {
            $table->index(['user_id', 'post_id'], 'comments_user_post_index');
        });

        Schema::table(Table::BOOKMARKS, function (Blueprint $table) {
            $table->index(['bookmarkable_type', 'bookmarkable_id'], 'bookmarks_bookmarkable_index');
        });

        Schema::table(Table::REACTIONS, function (Blueprint $table) {
            $table->index(['reactable_type', 'reactable_id'], 'reactions_reactable_index');
        });

        Schema::table(Table::REPORTS, function (Blueprint $table) {
            $table->index(['reportable_type', 'reportable_id'], 'reports_reportable_index');
        });
    }

    public function down(): void
    {
        Schema::table(Table::REPORTS, function (Blueprint $table) {
            $table->dropIndex('reports_reportable_index');
        });

        Schema::table(Table::REACTIONS, function (Blueprint $table) {
            $table->dropIndex('reactions_reactable_index');
        });

        Schema::table(Table::BOOKMARKS, function (Blueprint $table) {
            $table->dropIndex('bookmarks_bookmarkable_index');
        });

        Schema::table(Table::COMMENTS, function (Blueprint $table) {
            $table->dropIndex('comments_user_post_index');
        });

        Schema::table(Table::POSTS, function (Blueprint $table) {
            $table->dropIndex('posts_feed_user_status_created_index');
            $table->dropIndex('posts_feed_status_created_id_index');
        });
    }
};
