<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderItemCharge;
use App\Models\Platform;
use App\Models\PlatformPayment;
use App\Models\PlatformSettlement;
use App\Models\Product;
use App\Models\User;
use App\Services\AccountingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesCommerce;
use Tests\TestCase;

class PlatformSettlementTest extends TestCase
{
    use CreatesCommerce;
    use RefreshDatabase;

    /**
     * A successful sale line on an order, with the fees the platform withheld.
     *
     * @param  array<int, array{0: string, 1: float}>  $charges
     */
    private function successfulSale(Platform $platform, Product $product, float $price, int $qty = 1, array $charges = [], array $orderOverrides = []): OrderItem
    {
        $order = Order::create(array_merge([
            'order_number' => 'ORD-'.uniqid(),
            'platform_id' => $platform->id,
            'customer_name' => 'Buyer',
            'status' => Order::STATUS_DELIVERED,
            'status_updated_at' => now(),
        ], $orderOverrides));

        $item = OrderItem::create([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'quantity' => $qty,
            'selling_price' => $price,
            'status' => 'successful',
        ]);

        foreach ($charges as [$name, $amount]) {
            OrderItemCharge::create([
                'order_item_id' => $item->id,
                'charge_name' => $name,
                'amount' => $amount,
            ]);
        }

        return $item;
    }

    private function recordSettlement(Platform $platform, array $overrides = [])
    {
        return $this->actingAs(User::factory()->create())->post(route('accounting.settlements.store'), array_merge([
            'platform_id' => $platform->id,
            'period_start' => now()->subWeek()->toDateString(),
            'period_end' => now()->toDateString(),
            'gross_amount' => 1000,
            'fees_amount' => 150,
        ], $overrides));
    }

    public function test_recording_a_settlement_derives_net_from_gross_minus_fees(): void
    {
        $platform = $this->makePlatform();

        $this->recordSettlement($platform)->assertRedirect(route('accounting.settlements'));

        $settlement = PlatformSettlement::firstOrFail();
        $this->assertSame('850.00', $settlement->net_amount);
        $this->assertSame(15.0, $settlement->feeRate());
        $this->assertFalse($settlement->isReceived());
    }

    public function test_an_explicit_net_is_respected(): void
    {
        $platform = $this->makePlatform();

        $this->recordSettlement($platform, ['net_amount' => 777.5]);

        $this->assertSame('777.50', PlatformSettlement::firstOrFail()->net_amount);
    }

    public function test_a_settlement_needs_a_platform_and_a_sane_period(): void
    {
        $platform = $this->makePlatform();
        $user = User::factory()->create();

        $this->actingAs($user)->post(route('accounting.settlements.store'), [
            'period_start' => now()->toDateString(),
            'period_end' => now()->subWeek()->toDateString(),
            'gross_amount' => 100,
        ])->assertSessionHasErrors(['platform_id', 'period_end']);

        $this->assertSame(0, PlatformSettlement::count());
    }

    public function test_marking_a_payout_received_stamps_the_date_and_can_be_undone(): void
    {
        $platform = $this->makePlatform();
        $this->recordSettlement($platform);
        $settlement = PlatformSettlement::firstOrFail();
        $user = User::factory()->create();

        $this->actingAs($user)
            ->patch(route('accounting.settlements.received', $settlement), ['received' => 1, 'received_on' => '2026-09-01'])
            ->assertRedirect(route('accounting.settlements'));

        $this->assertTrue($settlement->fresh()->isReceived());
        $this->assertSame('2026-09-01', $settlement->fresh()->received_on->toDateString());

        $this->actingAs($user)
            ->patch(route('accounting.settlements.received', $settlement), ['received' => 0]);

        $this->assertFalse($settlement->fresh()->isReceived());
    }

    public function test_a_settlement_can_be_edited_and_deleted(): void
    {
        $platform = $this->makePlatform();
        $this->recordSettlement($platform);
        $settlement = PlatformSettlement::firstOrFail();
        $user = User::factory()->create();

        $this->actingAs($user)->patch(route('accounting.settlements.update', $settlement), [
            'platform_id' => $platform->id,
            'period_start' => now()->subWeek()->toDateString(),
            'period_end' => now()->toDateString(),
            'gross_amount' => 2000,
            'fees_amount' => 200,
            'reference' => 'NEFT-42',
        ])->assertRedirect(route('accounting.settlements'));

        $settlement->refresh();
        $this->assertSame('1800.00', $settlement->net_amount);
        $this->assertSame('NEFT-42', $settlement->reference);

        $this->actingAs($user)
            ->delete(route('accounting.settlements.destroy', $settlement))
            ->assertRedirect(route('accounting.settlements'));

        $this->assertSame(0, PlatformSettlement::count());
    }

    public function test_money_owed_is_earned_net_of_fees_minus_what_was_banked(): void
    {
        $platform = $this->makePlatform('Amazon');
        $product = $this->makeProduct('SKU-1', cost: 100, price: 500);

        // Two successful sales at ₹500 with ₹80 of fees apiece → earned 1000 gross, 160 fees.
        $this->successfulSale($platform, $product, 500, 1, [['commission', 60], ['shipping', 20]]);
        $this->successfulSale($platform, $product, 500, 1, [['commission', 60], ['shipping', 20]]);

        $overview = app(AccountingService::class)->settlementOverview();
        $row = collect($overview['platforms'])->firstWhere('platform.name', 'Amazon');

        $this->assertSame(1000.0, $row['gross']);
        $this->assertSame(160.0, $row['fees']);
        $this->assertSame(840.0, $row['expected_net']);
        $this->assertSame(0.0, $row['banked']);
        $this->assertSame(840.0, $row['owed']);

        // A received payout for the same amount clears what is owed.
        PlatformSettlement::create([
            'platform_id' => $platform->id,
            'period_start' => now()->subWeek()->toDateString(),
            'period_end' => now()->toDateString(),
            'gross_amount' => 1000,
            'fees_amount' => 160,
            'net_amount' => 840,
            'received_on' => now()->toDateString(),
        ]);

        $row = collect(app(AccountingService::class)->settlementOverview()['platforms'])->firstWhere('platform.name', 'Amazon');
        $this->assertSame(840.0, $row['banked']);
        $this->assertSame(0.0, $row['owed']);

        // A manual payout recorded on the payments page banks money too.
        PlatformPayment::create([
            'platform_id' => $platform->id,
            'amount' => 100,
            'payment_date' => now()->toDateString(),
            'type' => PlatformPayment::TYPE_MANUAL,
        ]);

        // Banking more than the sales say floors "still owed" at zero rather than showing a negative.
        $row = collect(app(AccountingService::class)->settlementOverview()['platforms'])->firstWhere('platform.name', 'Amazon');
        $this->assertSame(940.0, $row['banked']);
        $this->assertSame(0.0, $row['owed']);
    }

    public function test_auto_created_order_payments_do_not_count_as_banked_money(): void
    {
        $platform = $this->makePlatform('Amazon');
        $product = $this->makeProduct('SKU-1', cost: 100, price: 500);
        $item = $this->successfulSale($platform, $product, 500, 1, [['commission', 50]]);

        // The sale books a payment row, but it is revenue recognised, not cash banked.
        PlatformPayment::create([
            'platform_id' => $platform->id,
            'order_id' => $item->order_id,
            'amount' => 450,
            'payment_date' => now()->toDateString(),
            'type' => PlatformPayment::TYPE_ORDER,
        ]);

        $row = collect(app(AccountingService::class)->settlementOverview()['platforms'])->firstWhere('platform.name', 'Amazon');

        $this->assertSame(450.0, $row['expected_net']);
        $this->assertSame(0.0, $row['banked']);
        $this->assertSame(450.0, $row['owed']);
    }

    public function test_cancelled_orders_do_not_inflate_what_is_owed(): void
    {
        $platform = $this->makePlatform('Amazon');
        $product = $this->makeProduct('SKU-1', cost: 100, price: 500);
        $item = $this->successfulSale($platform, $product, 500, 1, [['commission', 50]]);

        $item->order->update(['status' => Order::STATUS_CANCELLED]);

        $row = collect(app(AccountingService::class)->settlementOverview()['platforms'])->firstWhere('platform.name', 'Amazon');

        $this->assertSame(0.0, $row['gross']);
        $this->assertSame(0.0, $row['expected_net']);
        $this->assertSame(0.0, $row['owed']);
    }

    public function test_a_pending_payout_reports_how_overdue_it_is(): void
    {
        $platform = $this->makePlatform('Amazon');

        PlatformSettlement::create([
            'platform_id' => $platform->id,
            'period_start' => now()->subDays(60)->toDateString(),
            'period_end' => now()->subDays(45)->toDateString(),
            'gross_amount' => 500,
            'fees_amount' => 50,
            'net_amount' => 450,
            'expected_on' => now()->subDays(40)->toDateString(),
        ]);

        $row = collect(app(AccountingService::class)->settlementOverview()['platforms'])->firstWhere('platform.name', 'Amazon');

        $this->assertSame(1, $row['pending_count']);
        $this->assertSame(450.0, $row['pending_net']);
        $this->assertSame(40, $row['oldest_age_days']);
        $this->assertSame(450.0, $row['owed']);
    }

    public function test_suggest_reads_the_sales_for_a_platform_and_period(): void
    {
        $amazon = $this->makePlatform('Amazon');
        $flipkart = $this->makePlatform('Flipkart');
        $product = $this->makeProduct('SKU-1', cost: 100, price: 500);

        $this->successfulSale($amazon, $product, 500, 1, [['commission', 60], ['shipping', 20]]);
        $this->successfulSale($amazon, $product, 400, 2, [['commission', 50]]);
        $this->successfulSale($flipkart, $product, 900, 1, [['commission', 99]]);

        $this->getJson(route('accounting.settlements.suggest', [
            'platform_id' => $amazon->id,
            'from' => now()->subDay()->toDateString(),
            'to' => now()->toDateString(),
        ]))->assertUnauthorized();

        $this->actingAs(User::factory()->create())
            ->getJson(route('accounting.settlements.suggest', [
                'platform_id' => $amazon->id,
                'from' => now()->subDay()->toDateString(),
                'to' => now()->toDateString(),
            ]))
            ->assertOk()
            ->assertJson([
                'orders' => 2,
                'gross_amount' => 1300.0,
                'fees_amount' => 130.0,
                'net_amount' => 1170.0,
            ]);
    }

    public function test_suggest_rejects_a_missing_platform(): void
    {
        $this->actingAs(User::factory()->create())
            ->getJson(route('accounting.settlements.suggest', ['from' => now()->toDateString(), 'to' => now()->toDateString()]))
            ->assertStatus(422);
    }

    public function test_the_settlements_page_renders_and_needs_authentication(): void
    {
        $platform = $this->makePlatform('Amazon');
        $product = $this->makeProduct('SKU-1', cost: 100, price: 500);
        $this->successfulSale($platform, $product, 500, 1, [['commission', 60]]);

        PlatformSettlement::create([
            'platform_id' => $platform->id,
            'period_start' => now()->subWeek()->toDateString(),
            'period_end' => now()->toDateString(),
            'gross_amount' => 500,
            'fees_amount' => 60,
            'net_amount' => 440,
        ]);

        $this->get(route('accounting.settlements'))->assertRedirect(route('login'));

        $this->actingAs(User::factory()->create())
            ->get(route('accounting.settlements'))
            ->assertOk()
            ->assertSee('Money owed to you')
            ->assertSee('Amazon')
            ->assertSee('Pending');
    }

    public function test_guests_cannot_record_or_change_settlements(): void
    {
        $platform = $this->makePlatform();
        $settlement = PlatformSettlement::create([
            'platform_id' => $platform->id,
            'period_start' => now()->toDateString(),
            'period_end' => now()->toDateString(),
            'gross_amount' => 100,
            'fees_amount' => 0,
            'net_amount' => 100,
        ]);

        $this->post(route('accounting.settlements.store'), [])->assertRedirect(route('login'));
        $this->patch(route('accounting.settlements.received', $settlement), ['received' => 1])->assertRedirect(route('login'));
        $this->delete(route('accounting.settlements.destroy', $settlement))->assertRedirect(route('login'));

        $this->assertSame(1, PlatformSettlement::count());
        $this->assertFalse($settlement->fresh()->isReceived());
    }

    public function test_the_overview_costs_a_constant_number_of_queries(): void
    {
        $service = app(AccountingService::class);
        $platform = $this->makePlatform('Amazon');

        \DB::enableQueryLog();
        $service->settlementOverview();
        $baseline = count(\DB::getQueryLog());

        // Four more platforms should not add per-platform queries.
        foreach (['Flipkart', 'Meesho', 'Myntra', 'Ajio'] as $name) {
            $this->makePlatform($name);
        }

        \DB::flushQueryLog();
        $service->settlementOverview();
        $after = count(\DB::getQueryLog());
        \DB::disableQueryLog();

        $this->assertGreaterThan(0, $baseline);
        $this->assertSame($baseline, $after);
    }
}
