<?php

namespace Tests\Feature;

use App\Models\GeneralExpense;
use App\Models\Order;
use App\Models\User;
use App\Services\AccountingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesCommerce;
use Tests\TestCase;

class AccountingTest extends TestCase
{
    use CreatesCommerce;
    use RefreshDatabase;

    private function summary(): array
    {
        return app(AccountingService::class)->ledgerSummary();
    }

    public function test_ledger_tracks_revenue_cogs_and_profit(): void
    {
        $this->actingAs(User::factory()->create());

        $platform = $this->makePlatform();
        $product = $this->makeProduct('ACC-1', cost: 100, price: 300);
        $this->addStockBatch($product, 10, 100);

        $this->post(route('orders.store'), [
            'platform_id' => $platform->id,
            'items' => [
                [
                    'product_id' => $product->id,
                    'quantity' => 2,
                    'selling_price' => 300,
                    'charges' => [['charge_name' => 'shipping', 'amount' => 40]],
                ],
            ],
        ]);

        $order = Order::first();
        $item = $order->items()->first();

        $this->patch(route('orders.update-item-status', [$order, $item]), ['status' => 'successful']);

        GeneralExpense::create([
            'description' => 'Rent',
            'amount' => 500,
            'category' => 'rent',
            'expense_date' => now()->toDateString(),
        ]);

        $summary = $this->summary();

        $this->assertSame(600.0, $summary['total_revenue']);       // 2 x 300
        $this->assertSame(200.0, $summary['total_cogs']);          // 2 x 100 (FIFO batch cost)
        $this->assertSame(40.0, $summary['total_charges']);
        $this->assertSame(500.0, $summary['total_expenses']);
        // 600 - 200 - 40 - 500
        $this->assertSame(-140.0, $summary['net_profit']);
    }

    public function test_pending_items_are_excluded_from_revenue_but_counted_as_expected(): void
    {
        $this->actingAs(User::factory()->create());

        $platform = $this->makePlatform();
        $product = $this->makeProduct('ACC-2', cost: 100, price: 300);
        $this->addStockBatch($product, 10, 100);

        $this->post(route('orders.store'), [
            'platform_id' => $platform->id,
            'items' => [['product_id' => $product->id, 'quantity' => 3, 'selling_price' => 300]],
        ]);

        $summary = $this->summary();

        $this->assertSame(0.0, $summary['total_revenue']);
        $this->assertSame(900.0, $summary['pending_revenue']);
        $this->assertSame(900.0, $summary['expected_revenue']);
    }

    public function test_return_revenue_is_reported(): void
    {
        $this->actingAs(User::factory()->create());

        $platform = $this->makePlatform();
        $product = $this->makeProduct('ACC-3', cost: 100, price: 300);
        $this->addStockBatch($product, 10, 100);

        $this->post(route('orders.store'), [
            'platform_id' => $platform->id,
            'items' => [['product_id' => $product->id, 'quantity' => 2, 'selling_price' => 300]],
        ]);

        $order = Order::first();
        $item = $order->items()->first();

        $this->patch(route('orders.update-item-status', [$order, $item]), [
            'status' => 'customer_return',
            'condition' => 'sellable',
        ]);

        $summary = $this->summary();

        $this->assertSame(2.0, $summary['returns_qty']);
        $this->assertSame(600.0, $summary['returns_revenue']); // 2 x 300
    }

    public function test_recording_a_payment_via_html_form_redirects(): void
    {
        $this->actingAs(User::factory()->create());
        $platform = $this->makePlatform();

        $this->post(route('accounting.payments.store'), [
            'platform_id' => $platform->id,
            'amount' => 1500,
            'payment_date' => now()->toDateString(),
        ])->assertRedirect(route('accounting.payments'));

        $this->assertDatabaseHas('platform_payments', [
            'platform_id' => $platform->id,
            'amount' => 1500,
        ]);
    }

    public function test_recording_an_expense_via_html_form_redirects(): void
    {
        $this->actingAs(User::factory()->create());

        $this->post(route('accounting.expenses.store'), [
            'description' => 'Packaging',
            'amount' => 250,
            'category' => 'packaging',
            'expense_date' => now()->toDateString(),
        ])->assertRedirect(route('accounting.expenses'));

        $this->assertDatabaseHas('general_expenses', ['description' => 'Packaging', 'amount' => 250]);
    }

    public function test_payment_endpoint_still_returns_json_for_ajax(): void
    {
        $this->actingAs(User::factory()->create());
        $platform = $this->makePlatform();

        $this->postJson(route('accounting.payments.store'), [
            'platform_id' => $platform->id,
            'amount' => 999,
            'payment_date' => now()->toDateString(),
        ])->assertCreated()->assertJsonPath('data.amount', '999.00');
    }
}
