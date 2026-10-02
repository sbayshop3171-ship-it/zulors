<?php

use App\Database\Configs\Table;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ad_engagements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ad_id')->constrained(Table::ADS)->cascadeOnDelete();
            $table->foreignId('user_id')->constrained(Table::USERS)->cascadeOnDelete();
            $table->foreignId('parent_id')->nullable()->constrained('ad_engagements')->cascadeOnDelete();
            $table->string('type', 24);
            $table->text('content')->nullable();
            $table->timestamps();
            $table->index(['ad_id', 'type']);
            $table->index(['ad_id', 'user_id', 'type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ad_engagements');
    }
};
