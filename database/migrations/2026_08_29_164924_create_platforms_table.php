<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('platforms', function (Blueprint $table) {
            $table->id();
            $table->string('name'); // Amazon, Flipkart, Meesho, etc.
            $table->string('slug')->unique();
            $table->json('charge_structure')->nullable(); // e.g. {"commission_percent": 5, "shipping_fee": 30, "gst_percent": 18}
            $table->boolean('is_active')->default(true);
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index('is_active');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('platforms');
    }
};
