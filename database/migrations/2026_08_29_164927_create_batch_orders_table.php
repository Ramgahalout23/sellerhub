<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('batch_orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('supplier_id')->constrained();
            $table->date('order_date');
            $table->decimal('total_cost', 12, 2)->default(0);
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index('supplier_id');
            $table->index('order_date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('batch_orders');
    }
};
