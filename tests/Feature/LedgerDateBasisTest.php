<?php

namespace Tests\Feature;

use App\Models\GeneralExpense;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderItemBatch;
use App\Models\OrderItemCharge;
use App\Models\Platform;
use App\Models\PlatformPayment;
use App\Models\Product;
use App\Models\StockBatch;
use App\Services\AccountingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\Concerns\CreatesCommerce;
use Tests\TestCase;

class LedgerDateBasisTest extends TestCase
{
    use CreatesCommerce;
    use RefreshDatabase;

    private function order(Platform $platform, Carbon $createdAt, array $attrs = []): Order
    {
        $order = Order::create(array_merge([
            'order_number' => 'ORD-'.uniqid(),
            'platform_id' => $platform->id,
            'customer_name' => 'Buyer',
            'status' => Order::STATUS_DELIVERED,
            'status_updated_at' => now(),
        ], $attrs));

        $order->forceFill(['created_at' => $createdAt])->save();

        return $order;
    }

    private function soldItem(Order $order, Product $product, int $qty, float $price, ?StockBatch $batch = null): OrderItem
    {
        $item = OrderItem::create([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'quantity' => $qty,
            'selling_price' => $price,
            'status' => 'successful',
        ]);

        if ($batch) {
            OrderItemBatch::create([
                'order_item_id' => $item->id,
                'stock_batch_id' => $batch->id,
                'quantity_deducted' => $qty,
            ]);
        }

        return $item;
    }

    public function test_revenue_cogs_and_charges_are_all_scoped_to_the_same_period(): void
    {
        $platform = $this->makePlatform();
        $product = $this->makeProduct('SKU-1', cost: 100, price: 300);
        $batch = $this->addStockBatch($product, 50, 100);

        $lastMonth = Carbon::now()->subMonthNoOverflow()->startOfMonth();

        // In period: 2 × 300 = 600 revenue, COGS 200, charges 30
        $inPeriod = $this->soldItem($this->order($platform, $lastMonth->copy()->addDays(2)), $product, 2, 300, $batch);
        OrderItemCharge::create(['order_item_id' => $inPeriod->id, 'charge_name' => 'commission', 'amount' => 30]);

        // Out of period: 10 × 300 = 3000 revenue, COGS 1000, charges 500 — must not leak in
        $outOfPeriod = $this->soldItem(
            $this->order($platform, Carbon::now()->subMonths(4)),
            $product, 10, 300, $batch
        );
        OrderItemCharge::create(['order_item_id' => $outOfPeriod->id, 'charge_name' => 'commission', 'amount' => 500]);

        $summary = app(AccountingService::class)->ledgerSummary(
            $lastMonth->toDateString(),
            $lastMonth->copy()->endOfMonth()->toDateString(),
        );

        $this->assertSame(600.0, $summary['total_revenue']);
        $this->assertSame(200.0, $summary['total_cogs'], 'COGS must follow the same period as revenue.');
        $this->assertSame(200.0, $summary['fifo_cogs']);
        $this->assertSame(30.0, $summary['total_charges']);
        // Net = 600 − 200 − 30
        $this->assertSame(370.0, $summary['net_profit']);
    }

    public function test_expenses_are_matched_on_expense_date_not_on_when_they_were_typed_in(): void
    {
        $lastMonth = Carbon::now()->subMonthNoOverflow();

        GeneralExpense::create([
            'description' => 'Rent for last month',
            'amount' => 5000,
            'category' => 'rent',
            'expense_date' => $lastMonth->copy()->startOfMonth()->addDays(3)->toDateString(),
        ]);

        $service = app(AccountingService::class);

        $matching = $service->ledgerSummary(
            $lastMonth->copy()->startOfMonth()->toDateString(),
            $lastMonth->copy()->endOfMonth()->toDateString(),
        );
        $this->assertSame(5000.0, $matching['total_expenses']);

        // A window that doesn't contain the expense date must not pick it up, even though
        // the row itself was created today.
        $other = $service->ledgerSummary(
            Carbon::now()->startOfMonth()->toDateString(),
            Carbon::now()->endOfMonth()->toDateString(),
        );
        $this->assertSame(0.0, $other['total_expenses']);
    }

    public function test_payments_are_matched_on_payment_date(): void
    {
        $platform = $this->makePlatform();
        $lastMonth = Carbon::now()->subMonthNoOverflow();

        PlatformPayment::create([
            'platform_id' => $platform->id,
            'type' => PlatformPayment::TYPE_MANUAL,
            'amount' => 2500,
            'payment_date' => $lastMonth->copy()->startOfMonth()->addDays(5)->toDateString(),
        ]);

        $service = app(AccountingService::class);

        $matching = $service->ledgerSummary(
            $lastMonth->copy()->startOfMonth()->toDateString(),
            $lastMonth->copy()->endOfMonth()->toDateString(),
        );
        $this->assertSame(2500.0, $matching['manual_payments']);
        $this->assertSame(2500.0, $matching['total_payments_received']);

        $other = $service->ledgerSummary(
            Carbon::now()->startOfMonth()->toDateString(),
            Carbon::now()->endOfMonth()->toDateString(),
        );
        $this->assertSame(0.0, $other['total_payments_received']);
    }

    public function test_a_range_includes_the_whole_of_its_end_date(): void
    {
        $platform = $this->makePlatform();
        $product = $this->makeProduct('SKU-1', cost: 100, price: 300);
        $batch = $this->addStockBatch($product, 10, 100);

        // Sold late on the final day of the range.
        $soldAt = Carbon::today()->setTime(23, 30);
        $this->soldItem($this->order($platform, $soldAt), $product, 1, 300, $batch);

        $today = Carbon::today()->toDateString();
        $summary = app(AccountingService::class)->ledgerSummary($today, $today);

        $this->assertSame(300.0, $summary['total_revenue'], 'The end date must be inclusive of the whole day.');
        $this->assertSame(100.0, $summary['total_cogs']);
    }

    public function test_stock_investment_uses_the_purchase_date(): void
    {
        $product = $this->makeProduct('SKU-1', cost: 100, price: 300);
        $lastMonth = Carbon::now()->subMonthNoOverflow()->startOfMonth();

        $batch = $this->addStockBatch($product, 10, 100); // purchased today
        $batch->batchOrderItem->batchOrder->forceFill(['order_date' => $lastMonth->copy()->addDays(2)])->save();

        $service = app(AccountingService::class);

        $last = $service->ledgerSummary(
            $lastMonth->toDateString(),
            $lastMonth->copy()->endOfMonth()->toDateString(),
        );
        $this->assertSame(1000.0, $last['total_invested']);

        $thisMonth = $service->ledgerSummary(
            Carbon::now()->startOfMonth()->toDateString(),
            Carbon::now()->endOfMonth()->toDateString(),
        );
        $this->assertSame(0.0, $thisMonth['total_invested']);
    }

    public function test_cancelled_orders_contribute_nothing_to_the_ledger(): void
    {
        $platform = $this->makePlatform();
        $product = $this->makeProduct('SKU-1', cost: 100, price: 300);
        $batch = $this->addStockBatch($product, 20, 100);

        $this->soldItem($this->order($platform, Carbon::now()), $product, 2, 300, $batch);
        $cancelled = $this->order($platform, Carbon::now(), ['status' => Order::STATUS_CANCELLED]);
        $this->soldItem($cancelled, $product, 5, 300, $batch);
        OrderItem::create([
            'order_id' => $cancelled->id,
            'product_id' => $product->id,
            'quantity' => 3,
            'selling_price' => 300,
            'status' => 'pending',
        ]);

        $summary = app(AccountingService::class)->ledgerSummary();

        // Only the live order counts: 2 × 300 revenue, 2 × 100 COGS.
        $this->assertSame(600.0, $summary['total_revenue']);
        $this->assertSame(200.0, $summary['total_cogs']);
        $this->assertSame(0.0, $summary['pending_revenue'], 'A cancelled order is not expected revenue.');
        $this->assertSame(400.0, $summary['expected_profit']);
    }

    public function test_an_open_ended_range_still_filters(): void
    {
        $platform = $this->makePlatform();
        $product = $this->makeProduct('SKU-1', cost: 100, price: 300);
        $batch = $this->addStockBatch($product, 20, 100);

        $this->soldItem($this->order($platform, Carbon::now()->subMonths(3)), $product, 1, 300, $batch);
        $this->soldItem($this->order($platform, Carbon::now()), $product, 1, 300, $batch);

        $onlyFrom = app(AccountingService::class)->ledgerSummary(Carbon::now()->subMonth()->toDateString(), null);
        $this->assertSame(300.0, $onlyFrom['total_revenue']);

        $onlyTo = app(AccountingService::class)->ledgerSummary(null, Carbon::now()->subMonth()->toDateString());
        $this->assertSame(300.0, $onlyTo['total_revenue']);
    }
}
