<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Tracks each purchase batch as a distinct stock pool
        Schema::create('stock_batches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('batch_order_item_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained();
            $table->foreignId('supplier_id')->constrained();
            $table->integer('original_quantity'); // Qty when purchased
            $table->integer('remaining_quantity'); // Qty still available
            $table->decimal('unit_cost', 10, 2); // Purchase price per unit
            $table->enum('status', ['available', 'partial', 'depleted'])->default('available');
            $table->timestamps();

            $table->index('product_id');
            $table->index('supplier_id');
            $table->index('status');
        });

        // Links order items to the batches they were fulfilled from (many-to-many for multi-batch splits)
        Schema::create('order_item_batches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_item_id')->constrained()->cascadeOnDelete();
            $table->foreignId('stock_batch_id')->constrained();
            $table->integer('quantity_deducted');
            $table->timestamps();

            $table->index('order_item_id');
            $table->index('stock_batch_id');
        });

        // Tracks which batch a return came from
        Schema::create('return_batches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_item_return_id')->constrained()->cascadeOnDelete();
            $table->foreignId('stock_batch_id')->constrained();
            $table->integer('quantity_returned');
            $table->timestamps();

            $table->index('order_item_return_id');
            $table->index('stock_batch_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('return_batches');
        Schema::dropIfExists('order_item_batches');
        Schema::dropIfExists('stock_batches');
    }
};
