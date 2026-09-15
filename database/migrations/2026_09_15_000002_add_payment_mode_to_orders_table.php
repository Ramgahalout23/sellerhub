<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            // How the customer pays: prepaid (online) or cash on delivery. Nearly all
            // RTO losses come from COD, so the two must be reported separately.
            // Existing orders default to prepaid.
            $table->string('payment_mode', 20)->default('prepaid');
            $table->index('payment_mode');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropIndex(['payment_mode']);
            $table->dropColumn('payment_mode');
        });
    }
};
