<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreSupplierRequest;
use App\Http\Requests\UpdateSupplierRequest;
use App\Models\Supplier;
use App\Services\SupplierService;
use Illuminate\Http\JsonResponse;

class SupplierController extends Controller
{
    public function __construct(
        protected SupplierService $service
    ) {}

    public function index()
    {
        $search = request('search');
        $suppliers = $this->service->paginated(15, $search);
        return view('suppliers.index', compact('suppliers'));
    }

    public function create()
    {
        return view('suppliers.create');
    }

    public function store(StoreSupplierRequest $request)
    {
        $supplier = $this->service->create($request->validated());
        if ($request->wantsJson()) {
            return response()->json(['message' => 'Supplier created.', 'data' => $supplier], 201);
        }
        return redirect()->route('suppliers.show', $supplier)->with('success', 'Supplier created successfully.');
    }

    public function show(Supplier $supplier)
    {
        $supplier->load(['products']);

        // Paginate batch orders: show 5 most recent initially
        $batchOrders = $supplier->batchOrders()
            ->with(['items.product'])
            ->recent()
            ->paginate(5)
            ->withQueryString();

        $totalBatchOrders = $supplier->batchOrders()->count();
        $totalPurchased = $supplier->batchOrders()->sum('total_cost');
        $totalUnits = 0;
        foreach ($supplier->batchOrders()->with('items')->get() as $bo) {
            $totalUnits += $bo->items->sum('quantity');
        }

        return view('suppliers.show', compact('supplier', 'batchOrders', 'totalBatchOrders', 'totalPurchased', 'totalUnits'));
    }

    public function edit(Supplier $supplier)
    {
        return view('suppliers.edit', compact('supplier'));
    }

    public function update(UpdateSupplierRequest $request, Supplier $supplier)
    {
        $supplier = $this->service->update($supplier, $request->validated());
        if ($request->wantsJson()) {
            return response()->json(['message' => 'Supplier updated.', 'data' => $supplier]);
        }
        return redirect()->route('suppliers.show', $supplier)->with('success', 'Supplier updated successfully.');
    }

    /**
     * AJAX endpoint: load more batch orders (for infinite scroll / load more)
     */
    public function loadMoreBatchOrders(Supplier $supplier)
    {
        $page = request('page', 2);
        $batchOrders = $supplier->batchOrders()
            ->with(['items.product'])
            ->recent()
            ->paginate(5, ['*'], 'page', $page);

        return response()->json([
            'html' => view('suppliers._batch-orders-list', ['batchOrders' => $batchOrders])->render(),
            'hasMore' => $batchOrders->hasMorePages(),
            'nextPage' => $batchOrders->currentPage() + 1,
        ]);
    }

    public function destroy(Supplier $supplier)
    {
        $this->service->delete($supplier);
        if (request()->wantsJson()) {
            return response()->json(['message' => 'Supplier deleted.']);
        }
        return redirect()->route('suppliers.index')->with('success', 'Supplier deleted successfully.');
    }
}
