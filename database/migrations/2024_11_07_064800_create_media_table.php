<?php
use App\Enums\Media\MediaType;
use App\Database\Configs\Table;
use App\Enums\Media\MediaStatus;
use App\Enums\Media\MediaVisibility;
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
        Schema::create(Table::MEDIA, function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('mediaable_id');
            $table->string('mediaable_type');
            $table->string('source_path')->default('');
            $table->string('thumbnail_path')->default('');
            $table->text('lqip_base64')->nullable();
            $table->string('type')->default(MediaType::IMAGE);
            $table->string('status')->default(MediaStatus::PROCESSED);
            $table->string('disk')->default('');
            $table->string('thumbnail_disk')->default('');
            $table->string('extension')->default('');
            $table->string('visibility')->default(MediaVisibility::VISIBLE);
            $table->string('mime')->default('');

            $table->string('size')->default('');
            $table->string('thumbnail_size')->default('');

            $table->integer('order')->default(0);
            $table->json('metadata')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists(Table::MEDIA);
    }
};
