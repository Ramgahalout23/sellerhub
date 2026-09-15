<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesCommerce;
use Tests\TestCase;

class OrderFlowTest extends TestCase
{
    use CreatesCommerce;
    use RefreshDatabase;

    public function test_creating_an_order_deducts_stock_fifo(): void
    {
        $this->actingAs(User::factory()->create());

        $platform = $this->makePlatform();
        $product = $this->makeProduct('FIFO-1', cost: 100, price: 300);

        $first = $this->addStockBatch($product, 5, 100);
        $second = $this->addStockBatch($product, 10, 120);
        // Oldest first: make sure ordering is deterministic.
        $first->forceFill(['created_at' => now()->subDays(2)])->save();

        $this->post(route('orders.store'), [
            'platform_id' => $platform->id,
            'customer_name' => 'Test Buyer',
            'items' => [
                ['product_id' => $product->id, 'quantity' => 8, 'selling_price' => 300],
            ],
        ])->assertRedirect();

        $order = Order::first();
        $this->assertNotNull($order);
        $item = $order->items()->first();
        $this->assertSame('pending', $item->status);

        // 5 from the first batch, 3 from the second.
        $this->assertSame(0, $first->fresh()->remaining_quantity);
        $this->assertSame(7, $second->fresh()->remaining_quantity);
        // 15 purchased - 8 sold = 7 remaining
        $this->assertSame(7, $product->fresh()->stock_quantity);
        $this->assertSame('depleted', $first->fresh()->status);

        $this->assertDatabaseHas('order_item_batches', [
            'order_item_id' => $item->id,
            'stock_batch_id' => $first->id,
            'quantity_deducted' => 5,
        ]);
        $this->assertDatabaseHas('order_item_batches', [
            'order_item_id' => $item->id,
            'stock_batch_id' => $second->id,
            'quantity_deducted' => 3,
        ]);
    }

    public function test_manual_batch_selection_is_respected(): void
    {
        $this->actingAs(User::factory()->create());

        $platform = $this->makePlatform();
        $product = $this->makeProduct('FIFO-2', cost: 100, price: 300);

        $oldest = $this->addStockBatch($product, 10, 100);
        $oldest->forceFill(['created_at' => now()->subDays(5)])->save();
        $newer = $this->addStockBatch($product, 10, 150);

        $this->post(route('orders.store'), [
            'platform_id' => $platform->id,
            'items' => [
                [
                    'product_id' => $product->id,
                    'quantity' => 2,
                    'selling_price' => 300,
                    'stock_batch_id' => $newer->id,
                ],
            ],
        ])->assertRedirect();

        // The explicitly chosen (newer) batch is drained first, not the oldest.
        $this->assertSame(8, $newer->fresh()->remaining_quantity);
        $this->assertSame(10, $oldest->fresh()->remaining_quantity);
    }

    public function test_successful_item_creates_an_auto_platform_payment(): void
    {
        $this->actingAs(User::factory()->create());

        $platform = $this->makePlatform();
        $product = $this->makeProduct('PAY-1', cost: 100, price: 300);
        $this->addStockBatch($product, 10, 100);

        $this->post(route('orders.store'), [
            'platform_id' => $platform->id,
            'items' => [['product_id' => $product->id, 'quantity' => 2, 'selling_price' => 250]],
        ])->assertRedirect();

        $order = Order::first();
        $item = $order->items()->first();

        $this->patch(route('orders.update-item-status', [$order, $item]), [
            'status' => 'successful',
        ])->assertRedirect();

        $this->assertDatabaseHas('platform_payments', [
            'platform_id' => $platform->id,
            'order_id' => $order->id,
            'type' => 'order',
            'amount' => 500,
        ]);
    }

    public function test_sellable_return_restores_stock_to_the_source_batch(): void
    {
        $this->actingAs(User::factory()->create());

        $platform = $this->makePlatform();
        $product = $this->makeProduct('RET-1', cost: 100, price: 300);
        $batch = $this->addStockBatch($product, 10, 100);

        $this->post(route('orders.store'), [
            'platform_id' => $platform->id,
            'items' => [['product_id' => $product->id, 'quantity' => 4, 'selling_price' => 300]],
        ])->assertRedirect();

        $order = Order::first();
        $item = $order->items()->first();
        $this->assertSame(6, $batch->fresh()->remaining_quantity);

        $this->patch(route('orders.update-item-status', [$order, $item]), [
            'status' => 'customer_return',
            'condition' => 'sellable',
            'return_charges' => 50,
        ])->assertRedirect();

        $this->assertSame('customer_return', $item->fresh()->status);
        $this->assertSame(10, $batch->fresh()->remaining_quantity);
        $this->assertSame(10, $product->fresh()->stock_quantity);

        $this->assertDatabaseHas('return_batches', [
            'stock_batch_id' => $batch->id,
            'quantity_returned' => 4,
        ]);
    }

    public function test_damaged_return_does_not_restore_stock(): void
    {
        $this->actingAs(User::factory()->create());

        $platform = $this->makePlatform();
        $product = $this->makeProduct('RET-2', cost: 100, price: 300);
        $batch = $this->addStockBatch($product, 10, 100);

        $this->post(route('orders.store'), [
            'platform_id' => $platform->id,
            'items' => [['product_id' => $product->id, 'quantity' => 4, 'selling_price' => 300]],
        ]);

        $order = Order::first();
        $item = $order->items()->first();

        $this->patch(route('orders.update-item-status', [$order, $item]), [
            'status' => 'customer_return',
            'condition' => 'damaged',
        ])->assertRedirect();

        $this->assertSame(6, $batch->fresh()->remaining_quantity);
        $this->assertSame(6, $product->fresh()->stock_quantity);
        $this->assertDatabaseCount('return_batches', 0);
    }

    public function test_missing_item_never_recovers_stock(): void
    {
        $this->actingAs(User::factory()->create());

        $platform = $this->makePlatform();
        $product = $this->makeProduct('MISS-1', cost: 100, price: 300);
        $batch = $this->addStockBatch($product, 10, 100);

        $this->post(route('orders.store'), [
            'platform_id' => $platform->id,
            'items' => [['product_id' => $product->id, 'quantity' => 3, 'selling_price' => 300]],
        ]);

        $order = Order::first();
        $item = $order->items()->first();

        $this->patch(route('orders.update-item-status', [$order, $item]), [
            'status' => 'missing',
        ])->assertRedirect();

        $this->assertSame(7, $batch->fresh()->remaining_quantity);
        $this->assertDatabaseCount('order_item_returns', 0);
    }

    public function test_item_from_another_order_cannot_be_updated(): void
    {
        $this->actingAs(User::factory()->create());

        $platform = $this->makePlatform();
        $product = $this->makeProduct('SEC-1');
        $this->addStockBatch($product, 10, 100);

        $this->post(route('orders.store'), [
            'platform_id' => $platform->id,
            'items' => [['product_id' => $product->id, 'quantity' => 1, 'selling_price' => 300]],
        ]);
        $this->post(route('orders.store'), [
            'platform_id' => $platform->id,
            'items' => [['product_id' => $product->id, 'quantity' => 1, 'selling_price' => 300]],
        ]);

        [$first, $second] = Order::all();
        $foreignItem = $second->items()->first();

        $this->patch(route('orders.update-item-status', [$first, $foreignItem]), [
            'status' => 'successful',
        ])->assertNotFound();
    }
}
