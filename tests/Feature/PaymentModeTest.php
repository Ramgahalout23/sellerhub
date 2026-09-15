<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Platform;
use App\Models\Product;
use App\Models\User;
use App\Services\InsightsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\Concerns\CreatesCommerce;
use Tests\TestCase;

class PaymentModeTest extends TestCase
{
    use CreatesCommerce;
    use RefreshDatabase;

    private function placeOrder(Platform $platform, Product $product, array $overrides = [])
    {
        return $this->actingAs(User::factory()->create())->post(route('orders.store'), array_merge([
            'platform_id' => $platform->id,
            'customer_name' => 'Buyer',
            'items' => [
                ['product_id' => $product->id, 'quantity' => 1, 'selling_price' => 300],
            ],
        ], $overrides));
    }

    private function import(string $csv, array $data = [])
    {
        return $this->actingAs(User::factory()->create())->post(route('orders.import.store'), array_merge([
            'file' => UploadedFile::fake()->createWithContent('orders.csv', $csv),
        ], $data));
    }

    private function order(Platform $platform, string $mode, array $itemSpecs): Order
    {
        $order = Order::create([
            'order_number' => 'ORD-'.uniqid(),
            'platform_id' => $platform->id,
            'customer_name' => 'Buyer',
            'payment_mode' => $mode,
            'status' => Order::STATUS_DELIVERED,
            'status_updated_at' => now(),
        ]);

        foreach ($itemSpecs as [$product, $qty, $status]) {
            OrderItem::create([
                'order_id' => $order->id,
                'product_id' => $product->id,
                'quantity' => $qty,
                'selling_price' => 300,
                'status' => $status,
            ]);
        }

        return $order;
    }

    public function test_an_order_records_how_the_customer_paid_and_defaults_to_prepaid(): void
    {
        $platform = $this->makePlatform();
        $product = $this->makeProduct('SKU-1', cost: 100, price: 300);
        $this->addStockBatch($product, 20, 100);

        $this->placeOrder($platform, $product, ['payment_mode' => 'cod', 'order_number' => 'COD-1']);
        $this->placeOrder($platform, $product, ['order_number' => 'PP-1']);

        $this->assertSame(Order::PAYMENT_MODE_COD, Order::where('order_number', 'COD-1')->firstOrFail()->payment_mode);
        $this->assertSame(Order::PAYMENT_MODE_PREPAID, Order::where('order_number', 'PP-1')->firstOrFail()->payment_mode);
    }

    public function test_a_bogus_payment_mode_is_rejected(): void
    {
        $platform = $this->makePlatform();
        $product = $this->makeProduct('SKU-1', cost: 100, price: 300);
        $this->addStockBatch($product, 20, 100);

        $this->placeOrder($platform, $product, ['payment_mode' => 'crypto'])
            ->assertSessionHasErrors('payment_mode');

        $this->assertSame(0, Order::count());
    }

    public function test_the_order_form_offers_cash_on_delivery(): void
    {
        $this->actingAs(User::factory()->create())
            ->get(route('orders.create'))
            ->assertOk()
            ->assertSee('Cash on Delivery')
            ->assertSee('name="payment_mode"', false);
    }

    public function test_orders_can_be_filtered_and_flagged_by_payment_mode(): void
    {
        $platform = $this->makePlatform();
        $product = $this->makeProduct('SKU-1', cost: 100, price: 300);
        $this->addStockBatch($product, 20, 100);

        $this->placeOrder($platform, $product, ['payment_mode' => 'cod', 'order_number' => 'COD-9']);
        $this->placeOrder($platform, $product, ['payment_mode' => 'prepaid', 'order_number' => 'PP-9']);

        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('orders.index', ['payment_mode' => 'cod']))
            ->assertOk()
            ->assertSee('COD-9')
            ->assertDontSee('PP-9');

        // The row is marked so COD orders stand out at a glance.
        $this->actingAs($user)
            ->get(route('orders.index'))
            ->assertOk()
            ->assertSee('COD');

        $this->actingAs($user)
            ->get(route('orders.show', Order::where('order_number', 'COD-9')->firstOrFail()))
            ->assertOk()
            ->assertSee('Cash on Delivery');
    }

    public function test_the_importer_maps_payment_columns_in_any_wording(): void
    {
        $this->makePlatform('Amazon');
        $product = $this->makeProduct('SKU-A', cost: 100, price: 300);
        $this->addStockBatch($product, 20, 100);

        $csv = "order_number,platform,sku,quantity,selling_price,payment_method\n".
            "AMZ-1,Amazon,SKU-A,1,300,Cash on Delivery\n".
            "AMZ-2,Amazon,SKU-A,1,300,Online Payment\n".
            "AMZ-3,Amazon,SKU-A,1,300,something-weird\n";

        $this->import($csv)->assertOk();

        $this->assertSame('cod', Order::where('order_number', 'AMZ-1')->firstOrFail()->payment_mode);
        $this->assertSame('prepaid', Order::where('order_number', 'AMZ-2')->firstOrFail()->payment_mode);
        // An unrecognised value falls back to the default rather than failing the row.
        $this->assertSame('prepaid', Order::where('order_number', 'AMZ-3')->firstOrFail()->payment_mode);
    }

    public function test_re_importing_corrects_the_payment_mode_of_an_existing_order(): void
    {
        $this->makePlatform('Amazon');
        $product = $this->makeProduct('SKU-A', cost: 100, price: 300);
        $this->addStockBatch($product, 20, 100);

        $this->import("order_number,platform,sku,quantity,selling_price\nAMZ-5,Amazon,SKU-A,1,300\n")->assertOk();
        $order = Order::where('order_number', 'AMZ-5')->firstOrFail();
        $this->assertSame('prepaid', $order->payment_mode);

        $this->import("order_number,platform,sku,quantity,selling_price,payment_mode\nAMZ-5,Amazon,SKU-A,1,300,COD\n")->assertOk();

        $this->assertSame('cod', $order->fresh()->payment_mode);
        $this->assertSame(1, Order::count());
    }

    public function test_insights_split_returns_and_rto_by_payment_mode(): void
    {
        $platform = $this->makePlatform();
        $product = $this->makeProduct('SKU-1', cost: 100, price: 300);

        // COD: one unit lost to RTO out of two dispatched.
        $this->order($platform, Order::PAYMENT_MODE_COD, [[$product, 1, 'rto'], [$product, 1, 'successful']]);
        // Prepaid: two units, both delivered.
        $this->order($platform, Order::PAYMENT_MODE_PREPAID, [[$product, 2, 'successful']]);

        $rows = collect(app(InsightsService::class)->paymentModeBreakdown())->keyBy('mode');

        $this->assertSame(1, $rows['cod']['orders']);
        $this->assertSame(2, $rows['cod']['dispatched_qty']);
        $this->assertSame(1, $rows['cod']['returned_qty']);
        $this->assertSame(1, $rows['cod']['rto_qty']);
        $this->assertSame(50.0, $rows['cod']['return_rate']);
        $this->assertSame(50.0, $rows['cod']['rto_rate']);
        $this->assertSame(300.0, $rows['cod']['revenue']);

        $this->assertSame(0.0, $rows['prepaid']['return_rate']);
        $this->assertSame(0.0, $rows['prepaid']['rto_rate']);
        $this->assertSame(600.0, $rows['prepaid']['revenue']);
    }

    public function test_cancelled_orders_are_excluded_from_the_payment_mode_split(): void
    {
        $platform = $this->makePlatform();
        $product = $this->makeProduct('SKU-1', cost: 100, price: 300);

        $order = $this->order($platform, Order::PAYMENT_MODE_COD, [[$product, 1, 'successful']]);
        $order->update(['status' => Order::STATUS_CANCELLED]);

        $this->assertSame([], app(InsightsService::class)->paymentModeBreakdown());
    }

    public function test_the_payment_modes_endpoint_responds_and_validates(): void
    {
        $platform = $this->makePlatform();
        $product = $this->makeProduct('SKU-1', cost: 100, price: 300);
        $this->order($platform, Order::PAYMENT_MODE_COD, [[$product, 1, 'successful']]);

        $this->getJson(route('insights.payment-modes'))->assertUnauthorized();

        $this->actingAs(User::factory()->create())
            ->getJson(route('insights.payment-modes'))
            ->assertOk()
            ->assertJsonStructure(['months', 'modes' => [['mode', 'label', 'orders', 'revenue', 'return_rate', 'rto_rate']]])
            ->assertJsonPath('modes.0.mode', 'cod');

        $this->getJson(route('insights.payment-modes', ['months' => 999]))->assertStatus(422);
    }
}
