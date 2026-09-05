<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->string('order_number')->nullable()->unique(); // Platform order ID
            $table->foreignId('product_id')->constrained();
            $table->foreignId('platform_id')->constrained();
            $table->string('customer_name')->nullable();
            $table->string('customer_phone')->nullable();
            $table->integer('quantity')->default(1);
            $table->decimal('selling_price', 10, 2); // Per unit price at which sold

            // Status lifecycle
            $table->enum('status', [
                'created',       // Sale just entered
                'shipped',       // Shipped out
                'in_transit',    // On the way
                'delivered',     // Reached customer
                'successful',    // Final - counted as sale
                'customer_return', // Returned after delivery
                'rto',           // Returned before delivery
                'missing',       // Lost in transit
            ])->default('created');

            $table->timestamp('shipped_at')->nullable();
            $table->timestamp('status_updated_at')->nullable();
            $table->timestamp('reminder_at')->nullable(); // Auto-set ~7 days after shipment
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index('product_id');
            $table->index('platform_id');
            $table->index('status');
            $table->index('reminder_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};
