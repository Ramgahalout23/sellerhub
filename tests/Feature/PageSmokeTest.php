<?php

namespace Tests\Feature;

use App\Models\Alert;
use App\Models\BatchOrder;
use App\Models\CustomField;
use App\Models\GeneralExpense;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Platform;
use App\Models\PlatformPayment;
use App\Models\Product;
use App\Models\StockBatch;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesCommerce;
use Tests\TestCase;

class PageSmokeTest extends TestCase
{
    use CreatesCommerce;
    use RefreshDatabase;

    private Product $product;

    private BatchOrder $batchOrder;

    private Order $order;

    private Platform $platform;

    private StockBatch $batch;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAs(User::factory()->create());

        $platform = $this->makePlatform();
        $supplier = Supplier::create(['name' => 'Demo Supplier', 'is_active' => true]);
        $product = $this->makeProduct('SMOKE-1', cost: 100, price: 300);
        $batch = $this->addStockBatch($product, 20, 100, $supplier);

        $order = Order::create([
            'order_number' => 'SMOKE-ORD-1',
            'platform_id' => $platform->id,
            'customer_name' => 'Test Customer',
            'status' => 'shipped',
            'shipped_at' => now()->subDays(2),
            'status_updated_at' => now(),
            'reminder_at' => now()->subDay(),
        ]);

        $item = OrderItem::create([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'quantity' => 2,
            'selling_price' => 300,
            'status' => 'pending',
        ]);
        $item->charges()->create(['charge_name' => 'shipping', 'amount' => 40]);

        PlatformPayment::create([
            'platform_id' => $platform->id,
            'order_id' => $order->id,
            'type' => 'order',
            'amount' => 500,
            'payment_date' => now()->toDateString(),
        ]);

        GeneralExpense::create([
            'description' => 'Rent',
            'amount' => 1000,
            'category' => 'rent',
            'expense_date' => now()->toDateString(),
        ]);

        Alert::create([
            'type' => Alert::TYPE_LOW_STOCK,
            'title' => 'Low stock',
            'message' => 'Something is low',
            'is_read' => false,
        ]);

        CustomField::create([
            'name' => 'Packing Charge',
            'slug' => 'packing_charge',
            'field_type' => 'number',
            'entity_type' => 'product',
            'sort_order' => 1,
            'is_active' => true,
        ]);

        $batchOrder = BatchOrder::create([
            'supplier_id' => $supplier->id,
            'order_date' => now()->toDateString(),
            'total_cost' => 2000,
        ]);
        $batchOrder->items()->create([
            'product_id' => $product->id,
            'quantity' => 20,
            'unit_cost' => 100,
            'total_cost' => 2000,
        ]);

        $this->product = $product;
        $this->batchOrder = $batchOrder;
        $this->order = $order;
        $this->platform = $platform;
        $this->batch = $batch;
    }

    public function test_all_main_pages_render(): void
    {
        $pages = [
            route('dashboard'),
            route('products.index'),
            route('products.create'),
            route('products.show', $this->product),
            route('products.edit', $this->product),
            route('products.low-stock'),
            route('suppliers.index'),
            route('suppliers.create'),
            route('batch-orders.index'),
            route('batch-orders.create'),
            route('batch-orders.show', $this->batchOrder),
            route('batch-orders.edit', $this->batchOrder),
            route('platforms.index'),
            route('platforms.create'),
            route('platforms.show', $this->platform),
            route('orders.index'),
            route('orders.create'),
            route('orders.import.create'),
            route('orders.show', $this->order),
            route('accounting.index'),
            route('accounting.payments'),
            route('accounting.expenses'),
            route('accounting.settlements'),
            route('insights.index'),
            route('alerts.index'),
        ];

        foreach ($pages as $url) {
            $this->get($url)->assertOk();
        }
    }

    public function test_supplier_detail_page_renders(): void
    {
        $supplier = Supplier::where('name', 'Demo Supplier')->firstOrFail();

        $this->get(route('suppliers.show', $supplier))->assertOk();
        $this->get(route('suppliers.edit', $supplier))->assertOk();
    }

    public function test_json_endpoints_respond(): void
    {
        $this->getJson(route('accounting.api'))->assertOk();
        $this->getJson(route('alerts.unread-count'))->assertOk();
        $this->getJson(route('products.summary', $this->product))->assertOk()->assertJsonStructure(['html', 'hasMore']);
        $this->getJson(route('orders.charge-preview', ['platform_id' => $this->platform->id, 'amount' => 600]))
            ->assertOk()->assertJsonStructure(['charges', 'total', 'net']);
        $this->getJson(route('orders.reminders'))->assertOk()->assertJsonStructure(['data']);
        $this->getJson(route('insights.rankings', ['type' => 'profit']))->assertOk();
        $this->getJson(route('insights.platform-comparison'))->assertOk();
        $this->getJson(route('insights.health-scores'))->assertOk();
        $this->getJson(route('insights.supplier-comparison', ['product_id' => $this->product->id]))->assertOk();
        $this->getJson(route('insights.time-trend', ['type' => 'product', 'entity_id' => $this->product->id]))->assertOk();
        $this->getJson(route('api.stock-batches', $this->product))->assertOk();
    }
}
