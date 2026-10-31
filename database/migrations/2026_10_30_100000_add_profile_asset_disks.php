<?php

use App\Database\Configs\Table;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table(Table::USERS, function (Blueprint $table) {
            $table->string('avatar_disk')->nullable();
            $table->string('cover_disk')->nullable();
        });

        Schema::table(Table::GROUPS, function (Blueprint $table) {
            $table->string('avatar_disk')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table(Table::GROUPS, function (Blueprint $table) {
            $table->dropColumn('avatar_disk');
        });

        Schema::table(Table::USERS, function (Blueprint $table) {
            $table->dropColumn(['avatar_disk', 'cover_disk']);
        });
    }
};
