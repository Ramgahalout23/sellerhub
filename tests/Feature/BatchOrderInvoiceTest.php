<?php

namespace Tests\Feature;

use App\Models\BatchOrder;
use App\Models\BatchOrderInvoice;
use App\Models\Product;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\CreatesCommerce;
use Tests\TestCase;

class BatchOrderInvoiceTest extends TestCase
{
    use CreatesCommerce;
    use RefreshDatabase;

    private Supplier $supplier;

    private Product $product;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('invoices');
        Storage::fake('public');

        $this->actingAs(User::factory()->create());

        $this->supplier = Supplier::create(['name' => 'Invoice Supplier', 'is_active' => true]);
        $this->product = $this->makeProduct('INV-1', cost: 100, price: 300);
    }

    private function payload(array $overrides = []): array
    {
        return array_merge([
            'supplier_id' => $this->supplier->id,
            'order_date' => now()->toDateString(),
            'items' => [
                ['product_id' => $this->product->id, 'quantity' => 5, 'unit_cost' => 100],
            ],
        ], $overrides);
    }

    private function pdf(string $name = 'supplier-bill.pdf'): UploadedFile
    {
        return UploadedFile::fake()->create($name, 100, 'application/pdf');
    }

    /**
     * A page image without needing the GD extension.
     */
    private function png(string $name = 'page-1.png'): UploadedFile
    {
        return UploadedFile::fake()->createWithContent(
            $name,
            base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg=='),
        );
    }

    public function test_multiple_invoice_files_can_be_uploaded_at_once(): void
    {
        $this->post(route('batch-orders.store'), $this->payload([
            'invoices' => [
                $this->pdf('page-1.pdf'),
                $this->png('page-2.png'),
                $this->png('page-3.png'),
            ],
        ]))->assertRedirect();

        $batchOrder = BatchOrder::firstOrFail();

        $this->assertCount(3, $batchOrder->invoices);
        $this->assertSame(
            ['page-1.pdf', 'page-2.png', 'page-3.png'],
            $batchOrder->invoices->pluck('original_name')->all(),
        );

        foreach ($batchOrder->invoices as $invoice) {
            Storage::disk('invoices')->assertExists($invoice->path);
        }

        // Order is preserved for the gallery.
        $this->assertSame([0, 1, 2], $batchOrder->invoices->pluck('sort_order')->all());
    }

    public function test_a_batch_order_can_be_created_without_invoices(): void
    {
        $this->post(route('batch-orders.store'), $this->payload())->assertRedirect();

        $batchOrder = BatchOrder::firstOrFail();

        $this->assertFalse($batchOrder->hasInvoice());
        $this->assertDatabaseCount('batch_order_invoices', 0);
    }

    public function test_more_invoices_can_be_appended_on_update(): void
    {
        $this->post(route('batch-orders.store'), $this->payload([
            'invoices' => [$this->pdf('first.pdf')],
        ]));
        $batchOrder = BatchOrder::firstOrFail();

        $this->put(route('batch-orders.update', $batchOrder), $this->payload([
            'invoices' => [$this->pdf('second.pdf'), $this->pdf('third.pdf')],
        ]))->assertRedirect();

        $batchOrder->refresh();

        $this->assertCount(3, $batchOrder->invoices);
        $this->assertSame(
            ['first.pdf', 'second.pdf', 'third.pdf'],
            $batchOrder->invoices->pluck('original_name')->all(),
        );
    }

    public function test_an_invoice_appears_in_the_gallery_on_the_show_page(): void
    {
        $this->post(route('batch-orders.store'), $this->payload([
            'invoices' => [$this->pdf('gallery-bill.pdf')],
        ]));
        $batchOrder = BatchOrder::firstOrFail();

        $this->get(route('batch-orders.show', $batchOrder))
            ->assertOk()
            ->assertSee('gallery-bill.pdf')
            ->assertSee('1 file(s)');
    }

    public function test_each_invoice_can_be_downloaded_when_authenticated(): void
    {
        $this->post(route('batch-orders.store'), $this->payload([
            'invoices' => [$this->pdf('a.pdf'), $this->pdf('b.pdf')],
        ]));
        $batchOrder = BatchOrder::firstOrFail();

        foreach ($batchOrder->invoices as $invoice) {
            $this->get(route('batch-orders.invoices.download', [$batchOrder, $invoice]))->assertOk();
        }
    }

    public function test_invoice_download_requires_authentication(): void
    {
        $this->post(route('batch-orders.store'), $this->payload(['invoices' => [$this->pdf()]]));
        $batchOrder = BatchOrder::firstOrFail();
        $invoice = $batchOrder->invoices->first();

        auth()->logout();

        $this->get(route('batch-orders.invoices.download', [$batchOrder, $invoice]))
            ->assertRedirect(route('login'));
    }

    public function test_an_invoice_from_another_batch_order_cannot_be_downloaded(): void
    {
        $this->post(route('batch-orders.store'), $this->payload(['invoices' => [$this->pdf('mine.pdf')]]));
        $this->post(route('batch-orders.store'), $this->payload(['invoices' => [$this->pdf('theirs.pdf')]]));

        [$first, $second] = BatchOrder::all();
        $foreignInvoice = $second->invoices->first();

        $this->get(route('batch-orders.invoices.download', [$first, $foreignInvoice]))->assertNotFound();
        $this->delete(route('batch-orders.invoices.destroy', [$first, $foreignInvoice]))->assertNotFound();
    }

    public function test_a_single_invoice_can_be_removed(): void
    {
        $this->post(route('batch-orders.store'), $this->payload([
            'invoices' => [$this->pdf('keep.pdf'), $this->pdf('remove.pdf')],
        ]));
        $batchOrder = BatchOrder::firstOrFail();
        $victim = $batchOrder->invoices->firstWhere('original_name', 'remove.pdf');
        $path = $victim->path;

        $this->delete(route('batch-orders.invoices.destroy', [$batchOrder, $victim]))->assertRedirect();

        Storage::disk('invoices')->assertMissing($path);
        $this->assertCount(1, $batchOrder->fresh()->invoices);
        $this->assertSame('keep.pdf', $batchOrder->fresh()->invoices->first()->original_name);
    }

    public function test_deleting_a_batch_order_removes_all_its_invoices(): void
    {
        $this->post(route('batch-orders.store'), $this->payload([
            'invoices' => [$this->pdf('one.pdf'), $this->pdf('two.pdf')],
        ]));
        $batchOrder = BatchOrder::firstOrFail();
        $paths = $batchOrder->invoices->pluck('path');

        $this->delete(route('batch-orders.destroy', $batchOrder))->assertRedirect();

        foreach ($paths as $path) {
            Storage::disk('invoices')->assertMissing($path);
        }

        $this->assertDatabaseCount('batch_orders', 0);
        $this->assertDatabaseCount('batch_order_invoices', 0);
    }

    public function test_non_document_files_are_rejected(): void
    {
        $this->post(route('batch-orders.store'), $this->payload([
            'invoices' => [UploadedFile::fake()->create('malware.exe', 10, 'application/x-msdownload')],
        ]))->assertSessionHasErrors('invoices.0');

        $this->assertDatabaseCount('batch_orders', 0);
        $this->assertDatabaseCount('batch_order_invoices', 0);
    }

    public function test_at_most_twenty_invoices_are_accepted(): void
    {
        $files = [];
        for ($i = 1; $i <= 21; $i++) {
            $files[] = $this->pdf("page-{$i}.pdf");
        }

        $this->post(route('batch-orders.store'), $this->payload(['invoices' => $files]))
            ->assertSessionHasErrors('invoices');

        $this->assertDatabaseCount('batch_orders', 0);
    }

    public function test_invoice_disk_is_private_and_not_web_served(): void
    {
        $root = config('filesystems.disks.invoices.root');

        $this->assertNotSame(storage_path('app/public'), $root);
        $this->assertStringNotContainsString('public', $root);
        $this->assertNotTrue(config('filesystems.disks.invoices.serve') ?? false);
    }

    public function test_invoice_helper_attributes(): void
    {
        $this->post(route('batch-orders.store'), $this->payload([
            'invoices' => [$this->pdf('helper.pdf'), $this->png('scan.png')],
        ]));

        $pdf = BatchOrderInvoice::where('original_name', 'helper.pdf')->firstOrFail();
        $img = BatchOrderInvoice::where('original_name', 'scan.png')->firstOrFail();

        $this->assertSame('pdf', $pdf->extension);
        $this->assertFalse($pdf->is_image);
        $this->assertSame('png', $img->extension);
        $this->assertTrue($img->is_image);
        $this->assertNotNull($img->human_size);
    }

    public function test_a_new_product_can_be_created_with_an_image_upload(): void
    {
        $this->post(route('batch-orders.store'), [
            'supplier_id' => $this->supplier->id,
            'order_date' => now()->toDateString(),
            'items' => [
                [
                    'product_name' => 'Brand New Widget',
                    'product_sku' => 'NEW-WIDGET-1',
                    'new_quantity' => 12,
                    'new_unit_cost' => 50,
                    'selling_price' => 120,
                    'product_image' => $this->png('widget.png'),
                ],
            ],
        ])->assertRedirect();

        $product = Product::where('sku', 'NEW-WIDGET-1')->firstOrFail();

        $this->assertNotNull($product->image);
        Storage::disk('public')->assertExists($product->image);
    }
}
