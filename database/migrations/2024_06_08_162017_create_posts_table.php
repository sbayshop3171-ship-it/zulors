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
        Schema::create(Table::POSTS, function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->foreign('user_id')->references('id')->on(Table::USERS)->onDelete('cascade');

            $table->unsignedBigInteger('quote_post_id')->nullable();
            $table->foreign('quote_post_id')->references('id')->on(Table::POSTS)->onDelete('no action');

            $table->string('title')->default('');
            $table->text('content');


            $table->string('status')->default('');
            $table->string('type')->default('');
            $table->string('text_language')->default('');

            // State values (Boolean)
            $table->boolean('edited')->default(false);
            $table->boolean('profile_pinned')->default(false);
            $table->boolean('global_pinned')->default(false);
            $table->boolean('is_sensitive')->default(false);
            $table->boolean('is_ai_generated')->default(false);

            // Counters values
            $table->integer('views_count')->default(0);
            $table->integer('comments_count')->default(0);
            $table->integer('shares_count')->default(0);
            $table->integer('bookmarks_count')->default(0);
            $table->integer('quotes_count')->default(0);
            $table->text('preview_lqip_base64')->nullable();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists(Table::POSTS);
    }
};
