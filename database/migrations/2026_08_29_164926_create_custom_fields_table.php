<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('custom_fields', function (Blueprint $table) {
            $table->id();
            $table->string('name'); // e.g. "packing_charge", "return_shipping"
            $table->string('slug')->unique();
            $table->string('field_type')->default('number'); // number, text, select, date, boolean
            $table->string('entity_type'); // product, sale_charge, return_charge
            $table->json('options')->nullable(); // For select fields: {"values": ["option1", "option2"]}
            $table->decimal('default_value', 10, 2)->nullable();
            $table->integer('sort_order')->default(0);
            $table->boolean('is_required')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['entity_type', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('custom_fields');
    }
};
