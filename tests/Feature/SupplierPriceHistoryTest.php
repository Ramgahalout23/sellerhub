<?php

namespace Tests\Feature;

use App\Models\BatchOrder;
use App\Models\Product;
use App\Models\Supplier;
use App\Models\User;
use App\Services\BatchOrderService;
use App\Services\ProductService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesCommerce;
use Tests\TestCase;

class SupplierPriceHistoryTest extends TestCase
{
    use CreatesCommerce;
    use RefreshDatabase;

    private function supplier(string $name): Supplier
    {
        return Supplier::create(['name' => $name, 'is_active' => true]);
    }

    /**
     * Record a real purchase through the service, the way the app does.
     */
    private function purchase(Product $product, int $quantity, float $unitCost, Supplier $supplier): BatchOrder
    {
        return app(BatchOrderService::class)->create([
            'supplier_id' => $supplier->id,
            'order_date' => now()->toDateString(),
            'total_cost' => $quantity * $unitCost,
        ], [
            ['product_id' => $product->id, 'quantity' => $quantity, 'unit_cost' => $unitCost],
        ]);
    }

    private function lastKnownPrice(Product $product): float
    {
        return (float) $product->fresh()->suppliers->first()->pivot->last_known_price;
    }

    public function test_every_purchase_refreshes_the_suppliers_last_known_price(): void
    {
        $product = $this->makeProduct('SKU-1', cost: 100, price: 300);
        $supplier = $this->supplier('Rajesh Traders');

        $this->purchase($product, 10, 100, $supplier);
        $this->assertEqualsWithDelta(100.0, $this->lastKnownPrice($product), 0.001);

        // Buying again at a new price must not leave the old figure behind.
        $this->purchase($product, 10, 118, $supplier);

        $this->assertEqualsWithDelta(118.0, $this->lastKnownPrice($product), 0.001);
        $this->assertSame(1, $product->fresh()->suppliers()->count());
    }

    public function test_editing_a_purchase_refreshes_the_price_too(): void
    {
        $product = $this->makeProduct('SKU-1', cost: 100, price: 300);
        $supplier = $this->supplier('Rajesh Traders');
        $batchOrder = $this->purchase($product, 10, 100, $supplier);

        app(BatchOrderService::class)->update($batchOrder, [
            'supplier_id' => $supplier->id,
            'order_date' => now()->toDateString(),
        ], [
            ['product_id' => $product->id, 'quantity' => 10, 'unit_cost' => 132.50],
        ]);

        $this->assertEqualsWithDelta(132.50, $this->lastKnownPrice($product), 0.001);
    }

    public function test_a_product_added_through_a_batch_order_is_linked_to_its_supplier(): void
    {
        $supplier = $this->supplier('Patel Wholesale');

        $this->actingAs(User::factory()->create())->post(route('batch-orders.store'), [
            'supplier_id' => $supplier->id,
            'order_date' => now()->toDateString(),
            'items' => [[
                'product_name' => 'Bulk Phone Stand',
                'product_sku' => 'ACC-STAND-1',
                'new_quantity' => 12,
                'new_unit_cost' => 85,
                'selling_price' => 249,
                'reorder_threshold' => 5,
            ]],
        ])->assertRedirect();

        $product = Product::where('sku', 'ACC-STAND-1')->firstOrFail();

        $this->assertSame($supplier->id, $product->suppliers->first()->id);
        $this->assertEqualsWithDelta(85.0, (float) $product->suppliers->first()->pivot->last_known_price, 0.001);
    }

    public function test_the_history_lists_suppliers_cheapest_first_with_their_trend(): void
    {
        $product = $this->makeProduct('SKU-1', cost: 100, price: 300);
        $cheap = $this->supplier('Cheap Co');
        $pricey = $this->supplier('Pricey Co');

        $this->addStockBatch($product, 10, 100, $cheap);
        $this->addStockBatch($product, 6, 110, $cheap);   // same supplier raised their price
        $this->addStockBatch($product, 8, 140, $pricey);

        $history = app(ProductService::class)->supplierPriceHistory($product);

        $this->assertCount(2, $history['suppliers']);
        $this->assertSame(3, $history['purchases']);

        $first = $history['suppliers'][0];
        $this->assertSame('Cheap Co', $first['supplier_name']);
        $this->assertSame(2, $first['purchases']);
        $this->assertSame(16, $first['total_qty']);
        $this->assertSame(110.0, $first['last_cost']);
        $this->assertSame(100.0, $first['previous_cost']);
        $this->assertSame(10.0, $first['change_percent']);
        $this->assertSame(100.0, $first['min_cost']);
        $this->assertSame(110.0, $first['max_cost']);
        $this->assertSame(105.0, $first['avg_cost']);
        $this->assertCount(2, $first['history']);

        $second = $history['suppliers'][1];
        $this->assertSame('Pricey Co', $second['supplier_name']);
        $this->assertNull($second['change_percent'], 'A first purchase has nothing to compare against.');

        $this->assertSame('Cheap Co', $history['cheapest']['supplier_name']);
        $this->assertSame(110.0, $history['cheapest_cost']);
        $this->assertSame(30.0, $history['spread']);
    }

    public function test_a_product_that_was_never_purchased_has_an_empty_history(): void
    {
        $product = $this->makeProduct('SKU-1', cost: 100, price: 300);

        $history = app(ProductService::class)->supplierPriceHistory($product);

        $this->assertSame([], $history['suppliers']);
        $this->assertNull($history['cheapest']);
        $this->assertNull($history['cheapest_cost']);
        $this->assertSame(0.0, $history['spread']);
        $this->assertSame(0, $history['purchases']);
    }

    public function test_the_product_page_highlights_the_cheapest_supplier(): void
    {
        $product = $this->makeProduct('SKU-1', cost: 100, price: 300);
        $this->addStockBatch($product, 10, 88, $this->supplier('Cheap Co'));
        $this->addStockBatch($product, 10, 140, $this->supplier('Pricey Co'));

        $this->actingAs(User::factory()->create())
            ->get(route('products.show', $product))
            ->assertOk()
            ->assertSee('Supplier Prices')
            ->assertSee('Cheapest now')
            ->assertSee('Cheap Co')
            ->assertSee('₹52', false); // the spread between them
    }

    public function test_the_history_is_built_from_purchases_not_from_a_separate_list(): void
    {
        $product = $this->makeProduct('SKU-1', cost: 100, price: 300);
        $supplier = $this->supplier('Rajesh Traders');
        $batch = $this->addStockBatch($product, 10, 95, $supplier);

        // Changing the purchase record changes the history — there is no second source.
        $batchOrder = BatchOrder::findOrFail($batch->batchOrderItem->batch_order_id);
        $batchOrder->items()->first()->update(['unit_cost' => 91, 'total_cost' => 910]);

        $history = app(ProductService::class)->supplierPriceHistory($product);

        $this->assertSame(91.0, $history['suppliers'][0]['last_cost']);
    }
}
