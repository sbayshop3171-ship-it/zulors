<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('media_publications', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->unsignedBigInteger('user_id')->index();
            $table->uuid('client_uid');
            $table->string('request_hash', 64);
            $table->string('kind', 16);
            $table->string('status', 24)->index();
            $table->boolean('has_video')->default(false);
            $table->json('payload');
            $table->json('profile');
            $table->json('result')->nullable();
            $table->string('error')->nullable();
            $table->timestamp('expires_at');
            $table->timestamp('published_at')->nullable();
            $table->timestamps();
            $table->unique(['user_id', 'client_uid']);
            $table->index(['has_video', 'status', 'created_at'], 'publication_admission_idx');
        });
        Schema::create('media_publication_items', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('publication_id');
            $table->foreign('publication_id')->references('id')->on('media_publications')->cascadeOnDelete();
            $table->uuid('client_uid');
            $table->unsignedInteger('position');
            $table->string('type', 16);
            $table->string('name');
            $table->string('mime', 128);
            $table->unsignedBigInteger('size');
            $table->string('status', 24)->default('pending');
            $table->unsignedInteger('generation')->default(0);
            $table->unsignedTinyInteger('progress')->default(0);
            $table->json('metadata');
            $table->json('upload')->nullable();
            $table->json('output')->nullable();
            $table->timestamp('dispatched_at')->nullable();
            $table->timestamp('queued_at')->nullable();
            $table->timestamp('original_deleted_at')->nullable();
            $table->timestamp('outputs_deleted_at')->nullable();
            $table->timestamps();
            $table->unique(['publication_id', 'client_uid']);
        });
        Schema::create('media_publication_events', function (Blueprint $table) {
            $table->id();
            $table->uuid('publication_id');
            $table->foreign('publication_id')->references('id')->on('media_publications')->cascadeOnDelete();
            $table->string('event_key')->unique();
            $table->string('type', 32);
            $table->unsignedBigInteger('recipient_id')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('media_publication_events');
        Schema::dropIfExists('media_publication_items');
        Schema::dropIfExists('media_publications');
    }
};
