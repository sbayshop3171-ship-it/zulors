<?php

use App\Enums\Ad\AdStatus;
use App\Enums\Ad\AdApproval;
use App\Database\Configs\Table;
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
        Schema::create(Table::ADS, function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->foreign('user_id')->references('id')->on(Table::USERS)->onDelete('cascade');
            $table->string('title')->nullable();
            $table->text('content')->nullable();
            $table->string('cta_text')->nullable();
            $table->string('status')->default(AdStatus::DRAFT);
            $table->string('type')->nullable();
            $table->decimal('total_budget', 10, 2)->default(0);
            $table->decimal('spent_budget', 10, 2)->default(0);
            $table->decimal('price_per_view', 10, 2)->default(config('ads.price_per_view'));
            $table->string('target_url')->nullable();
            $table->string('approval')->default(AdApproval::PENDING);
            $table->integer('views_count')->default(0);
            $table->integer('clicks_count')->default(0);
            $table->timestamp('last_show_at')->nullable();
            $table->timestamp('last_charge_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists(Table::ADS);
    }
};
