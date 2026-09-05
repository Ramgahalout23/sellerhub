<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // The app stores statuses like 'pending' that the original enum does not
        // contain. SQLite never enforced the enum, but MySQL truncates (errors).
        // Use a plain string so both drivers accept the same values.
        Schema::table('orders', function (Blueprint $table) {
            $table->string('status', 20)->default('created')->change();
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->enum('status', [
                'created',
                'shipped',
                'in_transit',
                'delivered',
                'successful',
                'customer_return',
                'rto',
                'missing',
            ])->default('created')->change();
        });
    }
};
