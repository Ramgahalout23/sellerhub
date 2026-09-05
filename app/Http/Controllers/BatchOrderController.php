<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreBatchOrderRequest;
use App\Models\BatchOrder;
use App\Models\Product;
use App\Services\BatchOrderService;
use App\Services\SupplierService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Str;

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
        unset($data['items']);

        // Process each item — create new products if needed
        $processedItems = [];
        foreach ($items as $index => $item) {
            $isExisting = !empty($item['product_id']);

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

        return redirect()
            ->route('batch-orders.show', $batchOrder)
            ->with('success', 'Batch order created successfully. ' . count($processedItems) . ' product(s) added to stock.');
    }

    public function show(BatchOrder $batchOrder)
    {
        $batchOrder->load(['supplier', 'items.product']);
        return view('batch-orders.show', compact('batchOrder'));
    }

    public function edit(BatchOrder $batchOrder)
    {
        $batchOrder->load(['supplier', 'items.product']);
        $suppliers = $this->supplierService->allActive();
        $products = Product::active()->orderBy('name')->get();

        return view('batch-orders.edit', compact('batchOrder', 'suppliers', 'products'));
    }

    public function update(StoreBatchOrderRequest $request, BatchOrder $batchOrder)
    {
        $data = $request->validated();
        $items = $data['items'] ?? [];
        unset($data['items']);

        $batchOrder = $this->service->update($batchOrder, $data, $items);

        return redirect()
            ->route('batch-orders.show', $batchOrder)
            ->with('success', 'Batch order updated successfully.');
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
        $this->service->delete($batchOrder);
        return redirect()
            ->route('batch-orders.index')
            ->with('success', 'Batch order deleted. Stock changes have been reversed.');
    }
}
