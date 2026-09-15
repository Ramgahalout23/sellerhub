<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderItemBatch;
use App\Models\Platform;
use App\Models\Product;
use App\Models\User;
use App\Services\InsightsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\CreatesCommerce;
use Tests\TestCase;

class PlatformInsightsTest extends TestCase
{
    use CreatesCommerce;
    use RefreshDatabase;

    private function makeOrder(Platform $platform, array $overrides = []): Order
    {
        return Order::create(array_merge([
            'order_number' => 'ORD-'.uniqid(),
            'platform_id' => $platform->id,
            'customer_name' => 'Buyer',
            'status' => Order::STATUS_DELIVERED,
            'status_updated_at' => now(),
        ], $overrides));
    }

    private function makeItem(Order $order, Product $product, int $quantity, float $price, string $status = 'successful'): OrderItem
    {
        return OrderItem::create([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'quantity' => $quantity,
            'selling_price' => $price,
            'status' => $status,
        ]);
    }

    public function test_it_ranks_platforms_by_net_profit_after_fees_and_cost(): void
    {
        $amazon = $this->makePlatform('Amazon');
        $flipkart = $this->makePlatform('Flipkart');
        $product = $this->makeProduct('SKU-1', cost: 100, price: 300);
        $batch = $this->addStockBatch($product, 20, 100);

        // Amazon: 2 x 300 = 600 revenue, 30 fees, 2 x 100 = 200 COGS -> 370 net
        $amazonItem = $this->makeItem($this->makeOrder($amazon), $product, 2, 300);
        $amazonItem->charges()->create(['charge_name' => 'commission', 'amount' => 30]);
        OrderItemBatch::create([
            'order_item_id' => $amazonItem->id,
            'stock_batch_id' => $batch->id,
            'quantity_deducted' => 2,
        ]);

        // Flipkart: 1 x 500 = 500 revenue, no fees, COGS falls back to cost_price 100 -> 400 net
        $this->makeItem($this->makeOrder($flipkart), $product, 1, 500);

        $rows = app(InsightsService::class)->platformComparison();

        $this->assertSame(['Flipkart', 'Amazon'], array_map(fn ($row) => $row['platform']->name, $rows));

        $this->assertSame(400.0, $rows[0]['net_profit']);
        $this->assertSame(0.0, $rows[0]['fees']);
        $this->assertSame(100.0, $rows[0]['cogs']);
        $this->assertSame(1, $rows[0]['orders']);
        $this->assertSame(400.0, $rows[0]['profit_per_order']);

        $this->assertSame(370.0, $rows[1]['net_profit']);
        $this->assertSame(600.0, $rows[1]['revenue']);
        $this->assertSame(30.0, $rows[1]['fees']);
        $this->assertSame(200.0, $rows[1]['cogs']);
        $this->assertEqualsWithDelta(61.7, $rows[1]['margin'], 0.1);
    }

    public function test_only_successful_items_count_as_revenue_and_returns_drive_the_return_rate(): void
    {
        $platform = $this->makePlatform('Amazon');
        $product = $this->makeProduct('SKU-1', cost: 100, price: 300);

        $order = $this->makeOrder($platform);
        $this->makeItem($order, $product, 2, 300, 'successful');
        $this->makeItem($order, $product, 5, 300, 'pending');   // not dispatched yet
        $returned = $this->makeItem($order, $product, 1, 300, 'customer_return');
        $returned->returnDetail()->create([
            'return_type' => 'customer_return',
            'condition' => 'sellable',
            'quantity_returned' => 1,
        ]);

        $row = app(InsightsService::class)->platformComparison()[0];

        $this->assertSame(600.0, $row['revenue']);
        $this->assertSame(2, $row['sold_qty']);
        // pending is excluded from the dispatched base, the return is included
        $this->assertSame(3, $row['dispatched_qty']);
        $this->assertSame(1, $row['returned_qty']);
        $this->assertEqualsWithDelta(33.3, $row['return_rate'], 0.1);
    }

    public function test_the_period_filter_restricts_orders_by_date(): void
    {
        $platform = $this->makePlatform('Amazon');
        $product = $this->makeProduct('SKU-1', cost: 100, price: 300);

        $recent = $this->makeOrder($platform);
        $this->makeItem($recent, $product, 1, 300, 'successful');

        $old = $this->makeOrder($platform, ['order_number' => 'ORD-OLD']);
        $this->makeItem($old, $product, 4, 300, 'successful');
        $old->forceFill(['created_at' => now()->subMonths(5)])->save();

        $service = app(InsightsService::class);

        $this->assertSame(1500.0, $service->platformComparison()[0]['revenue']);
        $this->assertSame(300.0, $service->platformComparison(2)[0]['revenue']);
    }

    public function test_platforms_without_sales_are_listed_so_they_are_visible(): void
    {
        $this->makePlatform('Amazon');
        $quiet = $this->makePlatform('Meesho');

        $rows = app(InsightsService::class)->platformComparison();

        $this->assertCount(2, $rows);
        $quietRow = collect($rows)->firstWhere(fn ($row) => $row['platform']->id === $quiet->id);
        $this->assertFalse($quietRow['has_sales']);
        $this->assertSame(0, $quietRow['orders']);
        $this->assertSame(0.0, $quietRow['net_profit']);
    }

    public function test_query_count_stays_flat_as_platforms_are_added(): void
    {
        $platform = $this->makePlatform('Amazon');
        $product = $this->makeProduct('SKU-1', cost: 100, price: 300);
        $this->makeItem($this->makeOrder($platform), $product, 1, 300, 'successful');

        $service = app(InsightsService::class);

        DB::flushQueryLog();
        DB::enableQueryLog();
        $service->platformComparison();
        $baseline = count(DB::getQueryLog());

        foreach (['Flipkart', 'Meesho', 'Shopify', 'WooCommerce'] as $name) {
            $this->makePlatform($name);
        }

        DB::flushQueryLog();
        $service->platformComparison();
        $withFivePlatforms = count(DB::getQueryLog());
        DB::disableQueryLog();

        $this->assertLessThanOrEqual(6, $baseline, 'The comparison should need only a handful of aggregate queries.');
        $this->assertSame($baseline, $withFivePlatforms, 'Adding platforms must not add queries (no N+1).');
    }

    public function test_the_endpoint_returns_a_platform_breakdown(): void
    {
        $platform = $this->makePlatform('Amazon');
        $product = $this->makeProduct('SKU-1', cost: 100, price: 300);
        $this->makeItem($this->makeOrder($platform), $product, 2, 300, 'successful');

        $this->actingAs(User::factory()->create())
            ->getJson(route('insights.platform-comparison'))
            ->assertOk()
            ->assertJsonStructure([
                'months',
                'platforms' => [[
                    'platform' => ['id', 'name'],
                    'orders', 'sold_qty', 'revenue', 'fees', 'net_profit',
                    'return_rate', 'margin', 'profit_per_order', 'has_sales',
                ]],
            ])
            ->assertJsonPath('platforms.0.platform.name', 'Amazon')
            ->assertJsonPath('platforms.0.net_profit', 400);
    }

    public function test_the_endpoint_validates_the_period_and_requires_auth(): void
    {
        $this->getJson(route('insights.platform-comparison'))->assertUnauthorized();

        $this->actingAs(User::factory()->create());
        $this->getJson(route('insights.platform-comparison', ['months' => 3]))
            ->assertOk()
            ->assertJsonPath('months', 3);
        $this->getJson(route('insights.platform-comparison', ['months' => 99]))->assertStatus(422);
        $this->getJson(route('insights.platform-comparison', ['months' => 'abc']))->assertStatus(422);
    }
}
