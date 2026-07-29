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
        Schema::create(Table::POST_POLLS, function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('post_id');
            $table->foreign('post_id')->references('id')->on(Table::POSTS)->onDelete('cascade');
            $table->json('choices')->nullable();
            $table->json('votes')->nullable();
            $table->json('metadata')->nullable();
            $table->boolean('is_anonymous')->default(false);
            $table->boolean('is_cancellable')->default(true);
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists(Table::POST_POLLS);
    }
};
