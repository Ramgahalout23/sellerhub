<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('sku')->unique();
            $table->text('description')->nullable();
            $table->decimal('cost_price', 10, 2)->default(0); // Average or base cost price
            $table->decimal('selling_price', 10, 2)->default(0);
            $table->integer('stock_quantity')->default(0);
            $table->integer('reorder_threshold')->default(5); // Custom per product
            $table->string('image')->nullable(); // Primary image path
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index('sku');
            $table->index('is_active');
            $table->index('stock_quantity');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
