<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\OrderItemBatch;
use App\Models\Platform;
use App\Models\Product;
use App\Models\StockBatch;
use App\Models\User;
use App\Services\AccountingService;
use App\Services\OrderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\Concerns\CreatesCommerce;
use Tests\TestCase;

class OrderCancellationTest extends TestCase
{
    use CreatesCommerce;
    use RefreshDatabase;

    private function sell(Platform $platform, Product $product, int $quantity = 3, string $number = 'CAN-1'): Order
    {
        return app(OrderService::class)->create([
            'order_number' => $number,
            'platform_id' => $platform->id,
            'customer_name' => 'Buyer',
            'status' => Order::STATUS_CREATED,
        ], [
            ['product_id' => $product->id, 'quantity' => $quantity, 'selling_price' => 300],
        ]);
    }

    public function test_cancelling_an_order_returns_its_stock_to_the_batches_it_came_from(): void
    {
        $platform = $this->makePlatform();
        $product = $this->makeProduct('SKU-1', cost: 100, price: 300);
        $batch = $this->addStockBatch($product, 10, 100);

        $order = $this->sell($platform, $product, 3);
        $item = $order->items->first();

        // Stock left the shelf when the sale was recorded.
        $this->assertSame(7, $product->fresh()->stock_quantity);
        $this->assertSame(7, $batch->fresh()->remaining_quantity);

        $this->actingAs(User::factory()->create())
            ->patch(route('orders.cancel', $order))
            ->assertRedirect(route('orders.show', $order));

        $this->assertSame(Order::STATUS_CANCELLED, $order->fresh()->status);
        $this->assertSame(10, $product->fresh()->stock_quantity);
        $this->assertSame(10, $batch->fresh()->remaining_quantity);
        $this->assertSame('available', $batch->fresh()->status);
        // Links are cleared so the same stock can never be returned twice.
        $this->assertSame(0, OrderItemBatch::where('order_item_id', $item->id)->count());
    }

    public function test_a_cancelled_order_stops_reminding_and_stops_counting_as_expected_revenue(): void
    {
        $platform = $this->makePlatform();
        $product = $this->makeProduct('SKU-1', cost: 100, price: 300);
        $this->addStockBatch($product, 10, 100);

        $order = $this->sell($platform, $product, 3);
        $order->forceFill(['status' => Order::STATUS_SHIPPED, 'reminder_at' => now()->subDay()])->save();

        $service = app(OrderService::class);
        $this->assertTrue($service->needsReminder()->contains('id', $order->id));

        $this->actingAs(User::factory()->create())->patch(route('orders.cancel', $order));

        $this->assertFalse(app(OrderService::class)->needsReminder()->contains('id', $order->id));
        $this->assertNull($order->fresh()->reminder_at);

        $summary = app(AccountingService::class)->ledgerSummary();
        $this->assertSame(0.0, $summary['pending_revenue']);
        $this->assertSame(0.0, $summary['expected_revenue']);
    }

    public function test_deleting_a_cancelled_order_does_not_return_the_stock_a_second_time(): void
    {
        $platform = $this->makePlatform();
        $product = $this->makeProduct('SKU-1', cost: 100, price: 300);
        $batch = $this->addStockBatch($product, 10, 100);

        $order = $this->sell($platform, $product, 4);

        $service = app(OrderService::class);
        $service->cancel($order);
        $this->assertSame(10, $product->fresh()->stock_quantity);

        $service->delete($order->fresh());

        $this->assertSame(10, $product->fresh()->stock_quantity);
        $this->assertSame(10, $batch->fresh()->remaining_quantity);
        $this->assertSame(0, Order::count());
    }

    public function test_cancelling_is_idempotent(): void
    {
        $platform = $this->makePlatform();
        $product = $this->makeProduct('SKU-1', cost: 100, price: 300);
        $this->addStockBatch($product, 10, 100);

        $order = $this->sell($platform, $product, 2);
        $service = app(OrderService::class);

        $service->cancel($order);
        $service->cancel($order->fresh());

        $this->assertSame(Order::STATUS_CANCELLED, $order->fresh()->status);
        $this->assertSame(10, $product->fresh()->stock_quantity);
    }

    public function test_a_cancelled_order_cannot_be_shipped_or_have_an_outcome_applied(): void
    {
        $platform = $this->makePlatform();
        $product = $this->makeProduct('SKU-1', cost: 100, price: 300);
        $this->addStockBatch($product, 10, 100);

        $order = $this->sell($platform, $product, 2);
        app(OrderService::class)->cancel($order);
        $item = $order->items()->first();

        $user = User::factory()->create();

        $this->actingAs($user)
            ->patch(route('orders.update-shipment-status', $order), ['status' => 'shipped'])
            ->assertRedirect()
            ->assertSessionHas('error');

        $this->actingAs($user)
            ->patch(route('orders.update-item-status', [$order, $item]), ['status' => 'successful'])
            ->assertRedirect()
            ->assertSessionHas('error');

        $order->refresh();
        $this->assertSame(Order::STATUS_CANCELLED, $order->status);
        $this->assertSame('pending', $item->fresh()->status);
        // No phantom payout was created for a sale that never happened.
        $this->assertDatabaseCount('platform_payments', 0);
    }

    public function test_orders_can_be_filtered_by_shipment_status_including_cancelled(): void
    {
        $platform = $this->makePlatform();
        $product = $this->makeProduct('SKU-1', cost: 100, price: 300);
        $this->addStockBatch($product, 20, 100);

        $cancelled = $this->sell($platform, $product, 1, 'CAN-1');
        $this->sell($platform, $product, 1, 'LIVE-1');
        app(OrderService::class)->cancel($cancelled);

        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('orders.index', ['order_status' => 'cancelled']))
            ->assertOk()
            ->assertSee('CAN-1')
            ->assertDontSee('LIVE-1');

        $this->actingAs($user)
            ->get(route('orders.index', ['order_status' => 'created']))
            ->assertOk()
            ->assertSee('LIVE-1')
            ->assertDontSee('CAN-1');
    }

    public function test_a_report_cannot_un_cancel_a_sale(): void
    {
        $platform = $this->makePlatform('Amazon');
        $product = $this->makeProduct('SKU-A', cost: 100, price: 300);
        $this->addStockBatch($product, 10, 100);

        $this->actingAs(User::factory()->create())
            ->post(route('orders.import.store'), ['file' => UploadedFile::fake()->createWithContent(
                'orders.csv',
                "order_number,platform,sku,quantity,selling_price,status\nAMZ-77,Amazon,SKU-A,1,300,shipped\n"
            )])
            ->assertOk();

        $order = Order::where('order_number', 'AMZ-77')->firstOrFail();
        app(OrderService::class)->cancel($order);
        $this->assertSame(10, $product->fresh()->stock_quantity);

        $this->actingAs(User::factory()->create())
            ->post(route('orders.import.store'), ['file' => UploadedFile::fake()->createWithContent(
                'orders.csv',
                "order_number,platform,sku,quantity,selling_price,status\nAMZ-77,Amazon,SKU-A,1,300,delivered\n"
            )])
            ->assertOk()
            ->assertSee('is cancelled here');

        $this->assertSame(Order::STATUS_CANCELLED, $order->fresh()->status);
        $this->assertSame(10, $product->fresh()->stock_quantity);
    }

    public function test_the_show_page_offers_cancelling_and_explains_a_cancelled_order(): void
    {
        $platform = $this->makePlatform();
        $product = $this->makeProduct('SKU-1', cost: 100, price: 300);
        $this->addStockBatch($product, 10, 100);

        $order = $this->sell($platform, $product, 1);
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('orders.show', $order))
            ->assertOk()
            ->assertSee('Cancel order');

        app(OrderService::class)->cancel($order);

        $this->actingAs($user)
            ->get(route('orders.show', $order))
            ->assertOk()
            ->assertSee('This order was cancelled')
            ->assertDontSee('Cancel order');
    }

    public function test_stock_batches_are_not_left_behind_after_cancelling(): void
    {
        $platform = $this->makePlatform();
        $product = $this->makeProduct('SKU-1', cost: 100, price: 300);
        $this->addStockBatch($product, 5, 100);
        $this->addStockBatch($product, 5, 120);

        // 7 units spans both batches.
        $order = $this->sell($platform, $product, 7);
        $this->assertSame(2, OrderItemBatch::count());

        app(OrderService::class)->cancel($order);

        $this->assertSame(10, $product->fresh()->stock_quantity);
        $this->assertSame(10, (int) StockBatch::sum('remaining_quantity'));
        $this->assertSame(0, OrderItemBatch::count());
    }
}
