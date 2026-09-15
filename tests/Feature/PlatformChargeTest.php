<?php

namespace Tests\Feature;

use App\Models\Platform;
use App\Models\User;
use App\Services\PlatformChargeCalculator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesCommerce;
use Tests\TestCase;

class PlatformChargeTest extends TestCase
{
    use CreatesCommerce;
    use RefreshDatabase;

    private function platformWith(array $structure, string $name = 'Amazon'): Platform
    {
        return Platform::create([
            'name' => $name,
            'slug' => strtolower($name).'-'.uniqid(),
            'is_active' => true,
            'charge_structure' => $structure,
        ]);
    }

    private function fullPlatform(): Platform
    {
        return $this->platformWith([
            'commission_percent' => 5,
            'shipping_fee' => 45,
            'closing_fee' => 25,
            'gst_percent' => 18,
        ]);
    }

    public function test_it_computes_percentage_and_flat_fees_with_gst_on_platform_fees(): void
    {
        $result = app(PlatformChargeCalculator::class)->forAmount($this->fullPlatform(), 1000);

        // commission = 5% of 1000 = 50, shipping 45, closing 25 -> fees 120
        // GST is charged on the fees (18% of 120 = 21.60), not on the product value.
        $this->assertSame(
            ['commission', 'shipping', 'closing', 'gst'],
            array_column($result['charges'], 'charge_name')
        );
        $this->assertEqualsWithDelta(50.0, $result['charges'][0]['amount'], 0.001);
        $this->assertEqualsWithDelta(21.6, $result['charges'][3]['amount'], 0.001);
        $this->assertEqualsWithDelta(141.6, $result['total'], 0.001);
        $this->assertEqualsWithDelta(858.4, $result['net'], 0.001);
    }

    public function test_fees_with_zero_amount_are_omitted_from_the_charges_list(): void
    {
        $result = app(PlatformChargeCalculator::class)
            ->forAmount($this->platformWith(['commission_percent' => 0]), 1000);

        $this->assertSame([], $result['charges']);
        $this->assertSame(0.0, $result['total']);
        $this->assertSame(1000.0, $result['net']);
    }

    public function test_flat_fees_still_apply_when_the_line_amount_is_zero(): void
    {
        $result = app(PlatformChargeCalculator::class)->forAmount($this->fullPlatform(), 0);

        // No commission on a zero sale, but shipping and closing are still billed.
        $this->assertSame(['shipping', 'closing', 'gst'], array_column($result['charges'], 'charge_name'));
        $this->assertEqualsWithDelta(82.6, $result['total'], 0.001);
        $this->assertEqualsWithDelta(-82.6, $result['net'], 0.001);
    }

    public function test_a_negative_amount_is_treated_as_zero(): void
    {
        $calculator = app(PlatformChargeCalculator::class);

        $this->assertSame(
            $calculator->forAmount($this->fullPlatform(), 0),
            $calculator->forAmount($this->fullPlatform(), -500)
        );
    }

    public function test_other_fee_is_included_in_gst_base_and_totals(): void
    {
        $platform = $this->platformWith([
            'commission_percent' => 10,
            'other_fee' => 20,
            'gst_percent' => 18,
        ]);

        $result = app(PlatformChargeCalculator::class)->forAmount($platform, 200);

        // commission 20, other 20 -> fees 40; GST 7.20
        $this->assertSame(['commission', 'gst', 'other'], array_column($result['charges'], 'charge_name'));
        $this->assertEqualsWithDelta(47.2, $result['total'], 0.001);
    }

    public function test_it_describes_the_charge_structure_in_plain_words(): void
    {
        $calculator = app(PlatformChargeCalculator::class);

        $this->assertSame(
            '5% commission + ₹45 shipping + ₹25 closing + 18% GST',
            $calculator->describe($this->fullPlatform())
        );
        $this->assertSame('', $calculator->describe($this->platformWith([])));
        $this->assertTrue($calculator->hasFees($this->fullPlatform()));
        $this->assertFalse($calculator->hasFees($this->platformWith([])));
    }

    public function test_charge_preview_endpoint_returns_charges_and_net(): void
    {
        $platform = $this->fullPlatform();

        $this->actingAs(User::factory()->create())
            ->getJson(route('orders.charge-preview', ['platform_id' => $platform->id, 'amount' => 1000]))
            ->assertOk()
            ->assertJsonStructure(['amount', 'charges' => [['charge_name', 'amount']], 'total', 'net'])
            ->assertJsonPath('total', 141.6)
            ->assertJsonPath('net', 858.4);
    }

    public function test_charge_preview_requires_auth_and_valid_input(): void
    {
        $platform = $this->fullPlatform();

        $this->getJson(route('orders.charge-preview', ['platform_id' => $platform->id, 'amount' => 100]))
            ->assertUnauthorized();

        $this->actingAs(User::factory()->create());

        $this->getJson(route('orders.charge-preview', ['amount' => 100]))->assertStatus(422);
        $this->getJson(route('orders.charge-preview', ['platform_id' => $platform->id]))->assertStatus(422);
        $this->getJson(route('orders.charge-preview', ['platform_id' => 99999, 'amount' => 100]))->assertStatus(422);
        $this->getJson(route('orders.charge-preview', ['platform_id' => $platform->id, 'amount' => 'free']))
            ->assertStatus(422);
    }

    public function test_order_form_shows_each_platform_s_charge_structure(): void
    {
        $this->fullPlatform();

        $this->actingAs(User::factory()->create())
            ->get(route('orders.create'))
            ->assertOk()
            ->assertSee('data-fees="5% commission + ₹45 shipping + ₹25 closing + 18% GST"', false)
            ->assertSee('orders/charge-preview', false)
            ->assertSee('chargeSummary', false);
    }
}
