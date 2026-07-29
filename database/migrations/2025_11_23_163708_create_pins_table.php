<?php

use App\Enums\Pin\PinType;
use App\Enums\Pin\PinContent;
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
        Schema::create(Table::PINS, function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained(Table::USERS);
            $table->string('pinnable_type');
            $table->string('type')->default(PinType::PROFILE);
            $table->string('content_type')->default(PinContent::POST);
            $table->unsignedBigInteger('pinnable_id');
            $table->unique(['user_id', 'pinnable_id', 'pinnable_type'], 'user_pin_unique');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists(Table::PINS);
    }
};
