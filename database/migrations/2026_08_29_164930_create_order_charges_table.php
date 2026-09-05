<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('order_charges', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->string('charge_name'); // e.g. "shipping", "gst", "ads", "packing", "commission"
            $table->decimal('amount', 10, 2);
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index('order_id');
            $table->index('charge_name');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_charges');
    }
};
