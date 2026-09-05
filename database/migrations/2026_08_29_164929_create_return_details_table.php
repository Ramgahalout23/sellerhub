<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('return_details', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->enum('return_type', ['customer_return', 'rto', 'missing']);
            $table->enum('condition', ['sellable', 'damaged'])->nullable(); // null for missing
            $table->integer('quantity_returned')->default(1);
            $table->decimal('return_charges', 10, 2)->default(0); // Shipping/RTO charges
            $table->text('notes')->nullable();
            $table->timestamp('received_at')->nullable(); // When item physically came back
            $table->timestamps();

            $table->index('order_id');
            $table->index('return_type');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('return_details');
    }
};
