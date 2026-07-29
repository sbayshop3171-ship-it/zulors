<?php

use App\Database\Configs\Table;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create(Table::POST_TOPICS, function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('post_id');
            $table->string('topic', 80);
            $table->string('source', 32)->default('hashtag');
            $table->decimal('weight', 8, 4)->default(1);
            $table->timestamps();

            $table->foreign('post_id')->references('id')->on(Table::POSTS)->onDelete('cascade');
            $table->unique(['post_id', 'topic'], 'post_topics_post_topic_unique');
            $table->index('topic', 'post_topics_topic_index');
            $table->index(['topic', 'created_at'], 'post_topics_topic_created_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists(Table::POST_TOPICS);
    }
};
