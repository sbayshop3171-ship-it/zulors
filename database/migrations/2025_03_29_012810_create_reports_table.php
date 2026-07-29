<?php

use App\Enums\ReportStatus;
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
        Schema::create(Table::REPORTS, function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('reporter_id');
            $table->foreign('reporter_id')->references('id')->on(Table::USERS)->onDelete('cascade');
            $table->unsignedBigInteger('reportable_id');
            $table->string('reportable_type');
            $table->string('status')->default(ReportStatus::PENDING);
            $table->integer('reason_index');
            $table->string('type');
            $table->text('reporter_comment')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists(Table::REPORTS);
    }
};
