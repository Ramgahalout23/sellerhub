<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('batch_order_invoices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('batch_order_id')->constrained()->cascadeOnDelete();
            // Path on the private "invoices" disk.
            $table->string('path');
            $table->string('original_name')->nullable();
            $table->string('mime_type')->nullable();
            $table->unsignedBigInteger('size')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index('batch_order_id');
        });

        // Carry over an invoice attached under the earlier single-file column, if present.
        if (Schema::hasColumn('batch_orders', 'invoice_path')) {
            foreach (DB::table('batch_orders')->whereNotNull('invoice_path')->get() as $row) {
                DB::table('batch_order_invoices')->insert([
                    'batch_order_id' => $row->id,
                    'path' => $row->invoice_path,
                    'original_name' => $row->invoice_original_name ?? null,
                    'sort_order' => 0,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            Schema::table('batch_orders', function (Blueprint $table) {
                $table->dropColumn(['invoice_path', 'invoice_original_name']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('batch_order_invoices');
    }
};
