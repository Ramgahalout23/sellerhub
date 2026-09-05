<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Step 1: Migrate existing orders → order_items (orders still has old columns)
        $orders = DB::table('orders')->whereNotNull('product_id')->get();
        foreach ($orders as $order) {
            $itemId = DB::table('order_items')->insertGetId([
                'order_id' => $order->id,
                'product_id' => $order->product_id,
                'quantity' => $order->quantity,
                'selling_price' => $order->selling_price,
                'status' => $this->mapStatus($order->status),
                'notes' => $order->notes,
                'created_at' => $order->created_at,
                'updated_at' => $order->updated_at,
            ]);

            // If order_charges still exists, migrate charges
            if (Schema::hasTable('order_charges') && Schema::hasColumn('order_charges', 'order_id')) {
                $chargesExist = DB::table('order_charges')->where('order_id', $order->id)->exists();
                if ($chargesExist) {
                    // Add order_item_id column if it doesn't exist
                    if (!Schema::hasColumn('order_charges', 'order_item_id')) {
                        Schema::table('order_charges', function (Blueprint $table) {
                            $table->foreignId('order_item_id')->nullable()->after('order_id');
                        });
                    }
                    DB::table('order_charges')
                        ->where('order_id', $order->id)
                        ->update(['order_item_id' => $itemId]);
                }
            }
        }

        // Step 2: Drop old tables if they still exist
        if (Schema::hasTable('return_details')) {
            Schema::dropIfExists('return_details');
        }
        if (Schema::hasTable('order_charges')) {
            Schema::dropIfExists('order_charges');
        }

        // Step 3: Remove old columns from orders
        if (Schema::hasColumn('orders', 'product_id')) {
            Schema::table('orders', function (Blueprint $table) {
                // MySQL: the FK must go before the index that backs it.
                // (On SQLite dropForeign is a no-op, so this is safe on both.)
                $table->dropForeign(['product_id']);
                $table->dropIndex('orders_product_id_index');
                $table->dropColumn(['product_id', 'quantity', 'selling_price']);
            });
        }
    }

    public function down(): void
    {
        // Reverse: re-create old columns on orders
        if (!Schema::hasColumn('orders', 'product_id')) {
            Schema::table('orders', function (Blueprint $table) {
                $table->foreignId('product_id')->nullable()->after('id');
                $table->integer('quantity')->default(1)->after('customer_phone');
                $table->decimal('selling_price', 10, 2)->default(0)->after('quantity');
            });
        }

        // Copy data back from order_items
        $items = DB::table('order_items')->get();
        foreach ($items as $item) {
            DB::table('orders')->where('id', $item->order_id)->update([
                'product_id' => $item->product_id,
                'quantity' => $item->quantity,
                'selling_price' => $item->selling_price,
            ]);
        }

        Schema::dropIfExists('order_item_returns');
        Schema::dropIfExists('order_item_charges');
        Schema::dropIfExists('order_items');
    }

    private function mapStatus(string $oldStatus): string
    {
        return match($oldStatus) {
            'created', 'shipped', 'in_transit', 'delivered' => 'pending',
            'successful' => 'successful',
            'customer_return' => 'customer_return',
            'rto' => 'rto',
            'missing' => 'missing',
            default => 'pending',
        };
    }
};
