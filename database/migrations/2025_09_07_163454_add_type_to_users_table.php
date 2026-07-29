<?php

use App\Enums\User\UserType;
use App\Database\Configs\Table;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table(Table::USERS, function (Blueprint $table) {
            $table->string('type')->default(UserType::READER);
        });
    }

    public function down(): void
    {
        Schema::table(Table::USERS, function (Blueprint $table) {
            $table->dropColumn('type');
        });
    }
};
