<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreProductRequest;
use App\Models\Product;
use App\Services\ProductService;
use App\Services\SupplierService;
use Illuminate\Http\JsonResponse;

class ProductController extends Controller
{
    public function __construct(
        protected ProductService $service,
        protected SupplierService $supplierService
    ) {}

    public function index()
    {
        $search = request('search');
        $supplierId = request('supplier_id');
        $products = $this->service->paginated(15, $search, $supplierId);
        $suppliers = $this->supplierService->allActive();

        return view('products.index', compact('products', 'suppliers'));
    }

    public function create()
    {
        $suppliers = $this->supplierService->allActive();
        $customFields = $this->service->productFields();

        return view('products.create', compact('suppliers', 'customFields'));
    }

    public function store(StoreProductRequest $request)
    {
        $data = $request->validated();
        $supplierIds = $data['supplier_ids'] ?? null;
        $customFieldValues = $data['custom_field_values'] ?? null;

        unset($data['supplier_ids'], $data['custom_field_values']);

        $product = $this->service->create($data, $supplierIds, $customFieldValues);

        if ($request->wantsJson()) {
            return response()->json(['message' => 'Product created.', 'data' => $product], 201);
        }

        return redirect()->route('products.show', $product)->with('success', 'Product created successfully.');
    }

    public function show(Product $product)
    {
        $summary = $this->service->getDetailSummary($product);

        return view('products.show', $summary);
    }

    public function edit(Product $product)
    {
        $product->load('suppliers');
        $suppliers = $this->supplierService->allActive();
        $customFields = $this->service->productFields();
        $customFieldValues = $product->customFieldValues->pluck('value', 'custom_field_id')->toArray();

        return view('products.edit', compact('product', 'suppliers', 'customFields', 'customFieldValues'));
    }

    public function update(StoreProductRequest $request, Product $product)
    {
        $data = $request->validated();
        $supplierIds = $data['supplier_ids'] ?? null;
        $customFieldValues = $data['custom_field_values'] ?? null;

        unset($data['supplier_ids'], $data['custom_field_values']);

        $product = $this->service->update($product, $data, $supplierIds, $customFieldValues);

        if ($request->wantsJson()) {
            return response()->json(['message' => 'Product updated.', 'data' => $product]);
        }

        return redirect()->route('products.show', $product)->with('success', 'Product updated successfully.');
    }

    public function destroy(Product $product)
    {
        $this->service->delete($product);

        if (request()->wantsJson()) {
            return response()->json(['message' => 'Product deleted.']);
        }

        return redirect()->route('products.index')->with('success', 'Product deleted successfully.');
    }

    public function lowStock()
    {
        $products = $this->service->lowStock();

        return view('products.low-stock', compact('products'));
    }

    public function detailSummary(Product $product): JsonResponse
    {
        $summary = $this->service->getDetailSummary($product);

        // The product page's "load more sales" JS expects pre-rendered rows.
        return response()->json([
            'html' => view('products._sale-rows', ['recent_orders' => $summary['recent_orders']])->render(),
            'hasMore' => $summary['recent_orders']->hasMorePages(),
            'data' => $summary,
        ]);
    }
}
