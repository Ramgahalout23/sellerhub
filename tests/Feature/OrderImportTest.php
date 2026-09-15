<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\Platform;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\Concerns\CreatesCommerce;
use Tests\TestCase;

class OrderImportTest extends TestCase
{
    use CreatesCommerce;
    use RefreshDatabase;

    private ?User $actor = null;

    private function upload(string $csv): UploadedFile
    {
        return UploadedFile::fake()->createWithContent('orders.csv', $csv);
    }

    private function importCsv(string $csv, array $data = [])
    {
        $this->actor ??= User::factory()->create();

        return $this->actingAs($this->actor)
            ->post(route('orders.import.store'), array_merge(['file' => $this->upload($csv)], $data));
    }

    private function feePlatform(): Platform
    {
        return Platform::create([
            'name' => 'Amazon',
            'slug' => 'amazon',
            'is_active' => true,
            'charge_structure' => [
                'commission_percent' => 5,
                'shipping_fee' => 45,
                'closing_fee' => 25,
                'gst_percent' => 18,
            ],
        ]);
    }

    public function test_rows_sharing_an_order_number_become_one_order_with_several_items(): void
    {
        $this->makePlatform();
        $productA = $this->makeProduct('SKU-A', 100, 300);
        $productB = $this->makeProduct('SKU-B', 50, 200);
        $this->addStockBatch($productA, 10, 100);
        $this->addStockBatch($productB, 10, 50);

        $csv = "order_number,platform,sku,quantity,selling_price,customer_name\n".
            "AMZ-1,Amazon,SKU-A,2,300,Asha Rao\n".
            "AMZ-1,Amazon,SKU-B,1,200,Asha Rao\n";

        $this->importCsv($csv)->assertOk();

        $order = Order::where('order_number', 'AMZ-1')->firstOrFail();
        $this->assertSame(2, $order->items()->count());
        $this->assertSame('Asha Rao', $order->customer_name);

        // FIFO deduction happened for both line items.
        $this->assertSame(8, $productA->fresh()->stock_quantity);
        $this->assertSame(9, $productB->fresh()->stock_quantity);
    }

    public function test_charges_are_auto_filled_from_the_platform_when_the_file_has_none(): void
    {
        $this->feePlatform();
        $product = $this->makeProduct('SKU-A', 400, 1000);
        $this->addStockBatch($product, 5, 400);

        $csv = "order_number,platform,sku,quantity,selling_price\n".
            "AMZ-2,Amazon,SKU-A,1,1000\n";

        $this->importCsv($csv)->assertOk()->assertSee('filled in from each platform');

        $item = Order::where('order_number', 'AMZ-2')->firstOrFail()->items->first();
        // commission 50 + shipping 45 + closing 25 + GST 18% of 120 = 141.60
        $this->assertEqualsWithDelta(141.6, (float) $item->charges()->sum('amount'), 0.01);
        $this->assertSame(
            ['commission', 'shipping', 'closing', 'gst'],
            $item->charges()->orderBy('id')->pluck('charge_name')->all()
        );
    }

    public function test_fee_columns_in_the_file_win_over_the_platform_structure(): void
    {
        $this->feePlatform();
        $product = $this->makeProduct('SKU-A', 400, 1000);
        $this->addStockBatch($product, 5, 400);

        $csv = "order_number,platform,sku,quantity,selling_price,commission,gst\n".
            "AMZ-3,Amazon,SKU-A,1,1000,10,1.80\n";

        $this->importCsv($csv)->assertOk();

        $item = Order::where('order_number', 'AMZ-3')->firstOrFail()->items->first();
        $this->assertEqualsWithDelta(11.80, (float) $item->charges()->sum('amount'), 0.01);
        $this->assertSame(['commission', 'gst'], $item->charges()->orderBy('id')->pluck('charge_name')->all());
    }

    public function test_re_importing_the_same_order_updates_status_without_duplicating_or_re_deducting_stock(): void
    {
        $this->makePlatform();
        $product = $this->makeProduct('SKU-A', 100, 300);
        $this->addStockBatch($product, 10, 100);

        $header = "order_number,platform,sku,quantity,selling_price,status\n";
        $this->importCsv($header."AMZ-4,Amazon,SKU-A,2,300,shipped\n")->assertOk();

        $order = Order::where('order_number', 'AMZ-4')->firstOrFail();
        $this->assertSame('shipped', $order->status);
        $this->assertSame(8, $product->fresh()->stock_quantity);

        $this->importCsv($header."AMZ-4,Amazon,SKU-A,2,300,delivered\n")
            ->assertOk()
            ->assertSee('Already present');

        $this->assertSame(1, Order::where('order_number', 'AMZ-4')->count());
        $this->assertSame('delivered', $order->fresh()->status);
        // Neither the items nor the stock are touched by the second pass.
        $this->assertSame(1, $order->fresh()->items()->count());
        $this->assertSame(8, $product->fresh()->stock_quantity);
    }

    public function test_dry_run_reports_everything_but_writes_nothing(): void
    {
        $this->makePlatform();
        $product = $this->makeProduct('SKU-A', 100, 300);
        $this->addStockBatch($product, 10, 100);

        $csv = "order_number,platform,sku,quantity,selling_price\n".
            "AMZ-5,Amazon,SKU-A,3,300\n";

        $this->importCsv($csv, ['dry_run' => 1])
            ->assertOk()
            ->assertSee('nothing was saved');

        $this->assertSame(0, Order::count());
        $this->assertSame(10, $product->fresh()->stock_quantity);
    }

    public function test_unmatched_skus_and_cancelled_rows_are_reported_while_the_rest_import(): void
    {
        $this->makePlatform();
        $product = $this->makeProduct('SKU-A', 100, 300);
        $this->addStockBatch($product, 10, 100);

        $csv = "order_number,platform,sku,quantity,selling_price,status\n".
            "AMZ-6,Amazon,SKU-NOPE,1,300,shipped\n".
            "AMZ-7,Amazon,SKU-A,1,300,cancelled\n".
            "AMZ-8,Amazon,SKU-A,1,300,shipped\n";

        $this->importCsv($csv)
            ->assertOk()
            ->assertSee('Unknown SKU')
            ->assertSee('is cancelled');

        $this->assertSame(0, Order::where('order_number', 'AMZ-6')->count());
        $this->assertSame(0, Order::where('order_number', 'AMZ-7')->count());
        $this->assertSame(1, Order::where('order_number', 'AMZ-8')->count());
        // Only the good row consumed stock.
        $this->assertSame(9, $product->fresh()->stock_quantity);
    }

    public function test_headers_are_matched_by_alias_regardless_of_order_and_punctuation(): void
    {
        $this->makePlatform();
        $product = $this->makeProduct('SKU-A', 100, 300);
        $this->addStockBatch($product, 10, 100);

        $csv = "Buyer Name,Order ID,Qty,Selling Price (INR),Seller SKU,Marketplace\n".
            "Ravi,AMZ-9,2,300,SKU-A,Amazon\n";

        $this->importCsv($csv)->assertOk();

        $order = Order::where('order_number', 'AMZ-9')->firstOrFail();
        $this->assertSame('Ravi', $order->customer_name);
        $this->assertSame(2, $order->items->first()->quantity);
    }

    public function test_unit_price_is_derived_when_only_a_line_total_is_given(): void
    {
        $this->makePlatform();
        $product = $this->makeProduct('SKU-A', 100, 300);
        $this->addStockBatch($product, 10, 100);

        $csv = "order_number,sku,quantity,total_price\n".
            "AMZ-10,SKU-A,3,900\n";

        $this->importCsv($csv, ['platform_id' => Platform::first()->id])->assertOk();

        $this->assertEquals(300.0, (float) Order::where('order_number', 'AMZ-10')->firstOrFail()->items->first()->selling_price);
    }

    public function test_orders_are_backdated_to_the_order_date_in_the_file(): void
    {
        $this->makePlatform();
        $product = $this->makeProduct('SKU-A', 100, 300);
        $this->addStockBatch($product, 10, 100);

        $csv = "order_number,platform,sku,quantity,selling_price,order_date\n".
            "AMZ-11,Amazon,SKU-A,1,300,2026-01-15\n";

        $this->importCsv($csv)->assertOk();

        $this->assertSame('2026-01-15', Order::where('order_number', 'AMZ-11')->firstOrFail()->created_at->toDateString());
    }

    public function test_a_missing_platform_column_falls_back_to_the_selected_platform(): void
    {
        $platform = $this->makePlatform();
        $product = $this->makeProduct('SKU-A', 100, 300);
        $this->addStockBatch($product, 10, 100);

        $csv = "order_number,sku,quantity,selling_price\n".
            "AMZ-12,SKU-A,1,300\n";

        $this->importCsv($csv, ['platform_id' => $platform->id])->assertOk();
        $this->assertSame($platform->id, Order::where('order_number', 'AMZ-12')->firstOrFail()->platform_id);
    }

    public function test_it_fails_loudly_on_a_file_it_cannot_read(): void
    {
        $this->makePlatform();
        $this->makeProduct('SKU-A', 100, 300);

        // No recognisable header row at all.
        $this->importCsv("hello,world\n1,2\n")
            ->assertRedirect()
            ->assertSessionHas('error');

        $this->assertSame(0, Order::count());
    }

    public function test_it_rejects_non_csv_uploads(): void
    {
        // A browser post bounces back with the errors banner; a JSON client gets 422.
        $this->actingAs(User::factory()->create())
            ->post(route('orders.import.store'), ['file' => UploadedFile::fake()->create('orders.xlsx', 5)])
            ->assertRedirect()
            ->assertSessionHasErrors('file');

        $this->actingAs(User::factory()->create())
            ->postJson(route('orders.import.store'), ['file' => UploadedFile::fake()->create('orders.xlsx', 5)])
            ->assertStatus(422)
            ->assertJsonValidationErrors('file');
    }

    public function test_the_template_download_offers_the_expected_columns(): void
    {
        $this->makePlatform();
        $this->makeProduct('SKU-A', 100, 300);

        $response = $this->actingAs(User::factory()->create())->get(route('orders.import.template'));

        $response->assertOk();
        $content = $response->streamedContent();

        $this->assertStringContainsString('order_number', $content);
        $this->assertStringContainsString('sku', $content);
        $this->assertStringContainsString('SKU-A', $content);
    }

    public function test_import_routes_require_authentication(): void
    {
        $this->get(route('orders.import.create'))->assertRedirect(route('login'));
        $this->post(route('orders.import.store'), [])->assertRedirect(route('login'));
        $this->get(route('orders.import.template'))->assertRedirect(route('login'));
    }

    public function test_import_page_renders_the_column_guide(): void
    {
        $this->actingAs(User::factory()->create())
            ->get(route('orders.import.create'))
            ->assertOk()
            ->assertSee('enctype="multipart/form-data"', false)
            ->assertSee('order_number')
            ->assertSee('Download a template');
    }
}
