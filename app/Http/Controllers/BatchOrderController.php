<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreBatchOrderRequest;
use App\Models\BatchOrder;
use App\Models\BatchOrderInvoice;
use App\Models\Product;
use App\Services\BatchOrderService;
use App\Services\SupplierService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class BatchOrderController extends Controller
{
    public function __construct(
        protected BatchOrderService $service,
        protected SupplierService $supplierService
    ) {}

    public function index()
    {
        $supplierId = request('supplier_id');
        $batchOrders = $this->service->paginated(15, $supplierId);
        $suppliers = $this->supplierService->allActive();

        return view('batch-orders.index', compact('batchOrders', 'suppliers'));
    }

    public function create()
    {
        $suppliers = $this->supplierService->allActive();
        $products = Product::active()->orderBy('name')->get();
        $selectedSupplierId = request('supplier_id');

        return view('batch-orders.create', compact('suppliers', 'products', 'selectedSupplierId'));
    }

    public function store(StoreBatchOrderRequest $request)
    {
        $data = $request->validated();
        $items = $data['items'];
        unset($data['items'], $data['invoices']);

        // Process each item — create new products if needed
        $processedItems = [];
        foreach ($items as $index => $item) {
            $isExisting = ! empty($item['product_id']);

            if ($isExisting) {
                // Existing product — just link it
                $processedItems[] = [
                    'product_id' => $item['product_id'],
                    'quantity' => $item['quantity'],
                    'unit_cost' => $item['unit_cost'],
                ];
            } else {
                // New product — create it first
                $product = Product::create([
                    'name' => $item['product_name'],
                    'sku' => $item['product_sku'],
                    'cost_price' => $item['new_unit_cost'],
                    'selling_price' => $item['selling_price'] ?? $item['new_unit_cost'],
                    'stock_quantity' => $item['new_quantity'],
                    'reorder_threshold' => $item['reorder_threshold'] ?? 5,
                    'is_active' => true,
                ]);

                // Handle image upload if provided
                if ($request->hasFile("items.{$index}.product_image")) {
                    $file = $request->file("items.{$index}.product_image");
                    $path = $file->store('products', 'public');
                    $product->update(['image' => $path]);
                }

                // Link supplier to product
                $product->suppliers()->attach($data['supplier_id'], [
                    'last_known_price' => $item['new_unit_cost'],
                ]);

                $processedItems[] = [
                    'product_id' => $product->id,
                    'quantity' => $item['new_quantity'],
                    'unit_cost' => $item['new_unit_cost'],
                ];
            }
        }

        $batchOrder = $this->service->create($data, $processedItems);

        $attached = $this->saveInvoices($request, $batchOrder);

        $message = 'Batch order created successfully. '.count($processedItems).' product(s) added to stock.';
        if ($attached > 0) {
            $message .= " {$attached} supplier invoice file(s) attached.";
        }

        return redirect()
            ->route('batch-orders.show', $batchOrder)
            ->with('success', $message);
    }

    public function show(BatchOrder $batchOrder)
    {
        $batchOrder->load(['supplier', 'items.product', 'invoices']);

        return view('batch-orders.show', compact('batchOrder'));
    }

    public function edit(BatchOrder $batchOrder)
    {
        $batchOrder->load(['supplier', 'items.product', 'invoices']);
        $suppliers = $this->supplierService->allActive();
        $products = Product::active()->orderBy('name')->get();

        return view('batch-orders.edit', compact('batchOrder', 'suppliers', 'products'));
    }

    public function update(StoreBatchOrderRequest $request, BatchOrder $batchOrder)
    {
        $data = $request->validated();
        $items = $data['items'] ?? [];
        unset($data['items'], $data['invoices']);

        $batchOrder = $this->service->update($batchOrder, $data, $items);

        $attached = $this->saveInvoices($request, $batchOrder);

        $message = 'Batch order updated successfully.';
        if ($attached > 0) {
            $message .= " {$attached} supplier invoice file(s) added.";
        }

        return redirect()
            ->route('batch-orders.show', $batchOrder)
            ->with('success', $message);
    }

    public function deleteItem(BatchOrder $batchOrder, $itemId)
    {
        $batchOrder = $this->service->deleteItem($batchOrder, $itemId);

        if (request()->wantsJson()) {
            return response()->json(['message' => 'Item removed and stock reversed.']);
        }

        return redirect()->route('batch-orders.edit', $batchOrder)
            ->with('success', 'Item removed. Stock has been adjusted.');
    }

    public function destroy(BatchOrder $batchOrder)
    {
        $this->deleteInvoiceFiles($batchOrder);
        $this->service->delete($batchOrder);

        return redirect()
            ->route('batch-orders.index')
            ->with('success', 'Batch order deleted. Stock changes have been reversed.');
    }

    /**
     * Stream an attached supplier invoice through an authenticated route.
     * The invoice disk is private, so it is never exposed by the web server.
     */
    public function downloadInvoice(BatchOrder $batchOrder, BatchOrderInvoice $invoice)
    {
        abort_unless(Storage::disk('invoices')->exists($invoice->path), 404);

        return Storage::disk('invoices')->response($invoice->path, $invoice->filename);
    }

    /**
     * Remove a single attached invoice file.
     */
    public function deleteInvoice(BatchOrder $batchOrder, BatchOrderInvoice $invoice)
    {
        Storage::disk('invoices')->delete($invoice->path);
        $invoice->delete();

        return redirect()
            ->back()
            ->with('success', 'Invoice removed.');
    }

    /**
     * Store one or more supplier invoice files against a batch order.
     *
     * @return int number of files stored
     */
    protected function saveInvoices(Request $request, BatchOrder $batchOrder): int
    {
        $files = $request->file('invoices');

        if (empty($files)) {
            return 0;
        }

        $files = is_array($files) ? $files : [$files];
        $nextOrder = (int) ($batchOrder->invoices()->max('sort_order') ?? -1) + 1;
        $saved = 0;

        foreach ($files as $file) {
            if (! $file || ! $file->isValid()) {
                continue;
            }

            $batchOrder->invoices()->create([
                'path' => $file->store('', 'invoices'),
                'original_name' => $file->getClientOriginalName(),
                'mime_type' => $file->getClientMimeType(),
                'size' => $file->getSize(),
                'sort_order' => $nextOrder++,
            ]);

            $saved++;
        }

        return $saved;
    }

    /**
     * Delete every invoice file belonging to a batch order.
     */
    protected function deleteInvoiceFiles(BatchOrder $batchOrder): void
    {
        foreach ($batchOrder->invoices as $invoice) {
            Storage::disk('invoices')->delete($invoice->path);
        }
    }
}
