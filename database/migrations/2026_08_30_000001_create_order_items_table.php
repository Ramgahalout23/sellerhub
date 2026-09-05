<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained();
            $table->integer('quantity')->default(1);
            $table->decimal('selling_price', 10, 2); // Per-unit price at time of sale

            // Per-item outcome status (order has shipment status, item has product outcome)
            $table->enum('status', [
                'pending',          // Awaiting fulfillment
                'successful',       // Final - counted as sale
                'customer_return',  // Returned after delivery
                'rto',              // Returned before delivery
                'missing',          // Lost in transit
            ])->default('pending');

            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index('order_id');
            $table->index('product_id');
            $table->index('status');
        });

        Schema::create('order_item_charges', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_item_id')->constrained()->cascadeOnDelete();
            $table->string('charge_name'); // shipping, gst, ads, packing, commission
            $table->decimal('amount', 10, 2);
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index('order_item_id');
            $table->index('charge_name');
        });

        Schema::create('order_item_returns', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_item_id')->constrained()->cascadeOnDelete();
            $table->string('return_type'); // customer_return, rto, missing
            $table->string('condition')->nullable(); // sellable, damaged
            $table->integer('quantity_returned')->default(0);
            $table->decimal('return_charges', 10, 2)->default(0);
            $table->text('notes')->nullable();
            $table->timestamp('received_at')->nullable();
            $table->timestamps();

            $table->index('order_item_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_item_returns');
        Schema::dropIfExists('order_item_charges');
        Schema::dropIfExists('order_items');
    }
};
