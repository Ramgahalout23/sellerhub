<?php

namespace Tests\Concerns;

use App\Models\BatchOrder;
use App\Models\BatchOrderItem;
use App\Models\Platform;
use App\Models\Product;
use App\Models\StockBatch;
use App\Models\Supplier;
use App\Models\User;

trait CreatesCommerce
{
    protected function admin(): User
    {
        return User::factory()->create();
    }

    protected function makePlatform(string $name = 'Amazon'): Platform
    {
        return Platform::create([
            'name' => $name,
            'slug' => strtolower($name),
            'charge_structure' => ['commission_percent' => 5],
            'is_active' => true,
        ]);
    }

    protected function makeProduct(string $sku = 'SKU-1', float $cost = 100, float $price = 300): Product
    {
        return Product::create([
            'name' => "Product {$sku}",
            'sku' => $sku,
            'cost_price' => $cost,
            'selling_price' => $price,
            'stock_quantity' => 0,
            'reorder_threshold' => 5,
            'is_active' => true,
        ]);
    }

    /**
     * Create a purchase batch which produces a StockBatch and increments product stock.
     */
    protected function addStockBatch(Product $product, int $quantity, float $unitCost, ?Supplier $supplier = null): StockBatch
    {
        $supplier ??= Supplier::create(['name' => 'Supplier '.uniqid(), 'is_active' => true]);

        $batchOrder = BatchOrder::create([
            'supplier_id' => $supplier->id,
            'order_date' => now()->toDateString(),
            'total_cost' => $quantity * $unitCost,
        ]);

        $item = BatchOrderItem::create([
            'batch_order_id' => $batchOrder->id,
            'product_id' => $product->id,
            'quantity' => $quantity,
            'unit_cost' => $unitCost,
            'total_cost' => $quantity * $unitCost,
        ]);

        $product->increment('stock_quantity', $quantity);

        return StockBatch::create([
            'batch_order_item_id' => $item->id,
            'product_id' => $product->id,
            'supplier_id' => $supplier->id,
            'original_quantity' => $quantity,
            'remaining_quantity' => $quantity,
            'unit_cost' => $unitCost,
            'status' => 'available',
        ]);
    }
}
