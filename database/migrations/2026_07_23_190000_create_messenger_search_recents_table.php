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
        Schema::create(Table::MESSENGER_SEARCH_RECENTS, function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('target_user_id');
            $table->timestamp('searched_at')->useCurrent();
            $table->timestamps();

            $table->foreign('user_id')->references('id')->on(Table::USERS)->onDelete('cascade');
            $table->foreign('target_user_id')->references('id')->on(Table::USERS)->onDelete('cascade');

            $table->unique(['user_id', 'target_user_id'], 'unique_messenger_search_recent');
            $table->index(['user_id', 'searched_at'], 'messenger_search_recents_user_searched_at_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists(Table::MESSENGER_SEARCH_RECENTS);
    }
};
