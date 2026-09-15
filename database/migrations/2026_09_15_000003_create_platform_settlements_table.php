<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('platform_settlements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('platform_id')->constrained()->cascadeOnDelete();

            // The window of sales this payout covers
            $table->date('period_start');
            $table->date('period_end');

            // What customers paid, what the platform kept, what should reach the bank
            $table->decimal('gross_amount', 12, 2)->default(0);
            $table->decimal('fees_amount', 12, 2)->default(0);
            $table->decimal('net_amount', 12, 2);

            $table->date('expected_on')->nullable();  // when it was due
            $table->date('received_on')->nullable();  // when it actually arrived (null = still owed)
            $table->string('reference')->nullable();  // bank / payout reference
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index('platform_id');
            $table->index('expected_on');
            $table->index('received_on');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('platform_settlements');
    }
};
