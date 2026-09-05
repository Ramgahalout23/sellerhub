<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('platform_payments', function (Blueprint $table) {
            $table->foreignId('order_id')->nullable()->after('platform_id')->constrained()->nullOnDelete();
            $table->string('type')->default('manual')->after('order_id'); // 'order' (auto) or 'manual' (settlement)
        });
    }

    public function down(): void
    {
        Schema::table('platform_payments', function (Blueprint $table) {
            $table->dropForeign(['order_id']);
            $table->dropColumn(['order_id', 'type']);
        });
    }
};
