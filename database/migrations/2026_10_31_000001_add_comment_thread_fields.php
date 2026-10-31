<?php

use App\Database\Configs\Table;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table(Table::COMMENTS, function (Blueprint $table) {
            $table->unsignedBigInteger('thread_root_id')->nullable()->after('parent_id');
            $table->timestamp('edited_at')->nullable()->after('text_language');
            $table->index(['post_id', 'parent_id', 'id'], 'comments_post_parent_id_index');
            $table->index(['post_id', 'thread_root_id', 'id'], 'comments_post_thread_id_index');
            $table->index(['thread_root_id', 'id'], 'comments_thread_id_index');
        });
    }

    public function down(): void
    {
        Schema::table(Table::COMMENTS, function (Blueprint $table) {
            $table->dropIndex('comments_post_parent_id_index');
            $table->dropIndex('comments_post_thread_id_index');
            $table->dropIndex('comments_thread_id_index');
            $table->dropColumn(['thread_root_id', 'edited_at']);
        });
    }
};
