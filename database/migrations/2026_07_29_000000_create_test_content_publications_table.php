<?php

use App\Database\Configs\Table;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create(Table::TEST_CONTENT_PUBLICATIONS, function (Blueprint $table) {
            $table->id();
            $table->string('campaign_key', 100);
            $table->foreignId('user_id')->constrained(Table::USERS)->cascadeOnDelete();
            $table->foreignId('post_id')->nullable()->constrained(Table::POSTS)->nullOnDelete();
            $table->string('content_key', 100);
            $table->string('status', 32)->default('reserved');
            $table->text('error_message')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->timestamps();

            $table->unique(['campaign_key', 'user_id']);
            $table->index(['campaign_key', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists(Table::TEST_CONTENT_PUBLICATIONS);
    }
};
