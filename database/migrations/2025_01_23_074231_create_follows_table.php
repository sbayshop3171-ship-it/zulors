<?php

use App\Database\Configs\Table;
use App\Enums\User\FollowStatus;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create(Table::FOLLOWS, function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('follower_id');
            $table->unsignedBigInteger('following_id');
            $table->string('status')->default(FollowStatus::FOLLOWING->value);
            $table->foreign('follower_id')->references('id')->on(Table::USERS)->onDelete('cascade');
            $table->foreign('following_id')->references('id')->on(Table::USERS)->onDelete('cascade');

            
            $table->unique(['follower_id', 'following_id'], 'unique_follow');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists(Table::FOLLOWS);
    }
};
