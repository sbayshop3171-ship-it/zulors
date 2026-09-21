<?php

use App\Database\Configs\Table;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ad_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ad_id')->constrained(Table::ADS)->cascadeOnDelete();
            $table->foreignId('campaign_id')->nullable()->constrained(Table::ADS)->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained(Table::USERS)->nullOnDelete();
            $table->string('event_type', 32);
            $table->string('placement', 24)->default('sidebar');
            $table->string('device', 16)->nullable();
            $table->decimal('watch_time_seconds', 10, 2)->nullable();
            $table->decimal('completion_rate', 6, 4)->nullable();
            $table->string('session_id', 120)->nullable();
            $table->timestamps();
            $table->index(['ad_id', 'event_type', 'placement']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ad_events');
    }
};