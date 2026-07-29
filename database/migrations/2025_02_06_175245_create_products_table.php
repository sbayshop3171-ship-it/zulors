<?php

use App\Database\Configs\Table;
use App\Enums\Product\ProductApproval;
use App\Enums\Product\ProductCondition;
use App\Enums\Product\ProductStatus;
use App\Enums\Product\ProductType;
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
        Schema::create(Table::PRODUCTS, function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('category_id')->nullable();
            $table->foreign('category_id')->references('id')->on(Table::CATEGORIES)->onDelete('no action');

            $table->unsignedBigInteger('store_id')->nullable();
            $table->foreign('store_id')->references('id')->on(Table::STORES)->onDelete('no action');

            $table->unsignedBigInteger('user_id');
            $table->foreign('user_id')->references('id')->on(Table::USERS)->onDelete('no action');

            $table->string('title')->default('');
            $table->text('description')->nullable();

            $table->string('approval')->default(ProductApproval::PENDING);
            $table->string('status')->default(ProductStatus::DRAFT);
            $table->string('condition')->default(ProductCondition::NEW);

            $table->string('currency')->default(config('app.default_currency'));

            $table->integer('stock_quantity')->default(0);
            
            $table->string('price')->default(0);
            $table->string('rating')->default(0);
            $table->string('discount')->default(0);
            $table->string('address')->nullable();
            $table->string('type')->default(ProductType::PHYSICAL);
            $table->integer('views_count')->default(0);
            $table->integer('contacts_count')->default(0);
            $table->integer('reviews_count')->default(0);
            $table->integer('bookmarks_count')->default(0);
            $table->timestamp('last_contacted_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists(Table::PRODUCTS);
    }
};
