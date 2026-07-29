<?php

use App\Enums\Job\JobType;
use App\Enums\Job\JobStatus;
use App\Enums\Job\JobApproval;
use App\Database\Configs\Table;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create(Table::JOB_LISTINGS, function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->foreign('user_id')->references('id')->on(Table::USERS)->onDelete('cascade');

            $table->unsignedBigInteger('category_id')->nullable();
            $table->foreign('category_id')->references('id')->on(Table::CATEGORIES)->onDelete('cascade');
            
            $table->string('title')->default('');
            $table->text('overview')->nullable();
            $table->text('description')->nullable();

            $table->string('status')->default(JobStatus::DRAFT);
            $table->integer('views_count')->default(0);
            $table->integer('applications_count')->default(0);
            $table->integer('bookmarks_count')->default(0);
            $table->string('income')->nullable();
            $table->boolean('is_start_income')->default(false);
            $table->string('currency')->default(config('app.default_currency'));
            $table->string('approval')->default(JobApproval::PENDING);

            $table->string('location')->nullable();
            
            $table->boolean('is_remote')->default(true);
            $table->boolean('is_urgent')->default(false);

            $table->string('type')->default(JobType::VACANCY);
            $table->timestamp('last_contacted_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists(Table::JOB_LISTINGS);
    }
};
