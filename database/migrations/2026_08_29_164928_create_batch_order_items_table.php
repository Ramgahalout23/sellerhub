<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('batch_order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('batch_order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained();
            $table->integer('quantity');
            $table->decimal('unit_cost', 10, 2);
            $table->decimal('total_cost', 10, 2); // quantity * unit_cost
            $table->timestamps();

            $table->index('batch_order_id');
            $table->index('product_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('batch_order_items');
    }
};
