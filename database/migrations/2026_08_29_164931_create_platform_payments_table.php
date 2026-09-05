<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('platform_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('platform_id')->constrained();
            $table->decimal('amount', 12, 2);
            $table->date('payment_date');
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index('platform_id');
            $table->index('payment_date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('platform_payments');
    }
};
