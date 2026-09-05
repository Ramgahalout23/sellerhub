<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('custom_field_values', function (Blueprint $table) {
            $table->id();
            $table->foreignId('custom_field_id')->constrained()->cascadeOnDelete();
            $table->string('entity_type'); // product, order_charge, return_charge
            $table->unsignedBigInteger('entity_id');
            $table->text('value')->nullable(); // All values stored as text, cast on use
            $table->timestamps();

            $table->index(['entity_type', 'entity_id']);
            $table->index('custom_field_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('custom_field_values');
    }
};
