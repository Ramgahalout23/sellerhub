<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreOrderRequest;
use App\Models\Order;
use App\Models\OrderItem;
use App\Services\OrderService;
use App\Services\ProductService;
use App\Services\PlatformService;
use Illuminate\Http\JsonResponse;

class OrderController extends Controller
{
    public function __construct(
        protected OrderService $service,
        protected ProductService $productService,
        protected PlatformService $platformService
    ) {}

    public function index()
    {
        $search = request('search');
        $status = request('status');
        $productId = request('product_id');
        $platformId = request('platform_id');

        $orders = $this->service->paginated(15, $status, $productId, $platformId, $search);
        $products = $this->productService->allActive();
        $platforms = $this->platformService->allActive();

        return view('orders.index', compact('orders', 'products', 'platforms'));
    }

    public function create()
    {
        $products = $this->productService->allActive();
        $platforms = $this->platformService->allActive();

        return view('orders.create', compact('products', 'platforms'));
    }

    public function store(StoreOrderRequest $request)
    {
        $data = $request->validated();
        $items = $data['items'];
        unset($data['items']);

        $order = $this->service->create($data, $items);

        if ($request->wantsJson()) {
            return response()->json([
                'message' => 'Order created.',
                'data' => $order->fresh(['items.product', 'platform']),
            ], 201);
        }

        return redirect()->route('orders.show', $order)->with('success', 'Order created successfully with ' . $order->items->count() . ' item(s).');
    }

    public function show(Order $order)
    {
        $order->load(['items.product', 'items.charges', 'items.returnDetail', 'platform']);
        return view('orders.show', compact('order'));
    }

    /**
     * Update the shipment status of the whole order
     */
    public function updateShipmentStatus(\Illuminate\Http\Request $request, Order $order)
    {
        $request->validate([
            'status' => 'required|in:shipped,in_transit,delivered',
        ]);

        $order = $this->service->updateShipmentStatus($order, $request->status);

        if ($request->wantsJson()) {
            return response()->json(['message' => "Order shipment status updated to '{$request->status}'."]);
        }

        return redirect()->route('orders.show', $order)->with('success', "Shipment status updated to {$request->status}.");
    }

    /**
     * Update a single line item's outcome status
     */
    public function updateItemStatus(\Illuminate\Http\Request $request, Order $order, OrderItem $item)
    {
        $request->validate([
            'status' => 'required|in:successful,customer_return,rto,missing',
            'return_type' => 'nullable|in:customer_return,rto,missing',
            'condition' => 'nullable|in:sellable,damaged',
            'return_charges' => 'nullable|numeric|min:0',
            'notes' => 'nullable|string',
        ]);

        $returnData = null;
        if (in_array($request->status, ['customer_return', 'rto', 'missing'])) {
            $returnData = [
                'return_type' => $request->return_type ?? $request->status,
                'condition' => $request->condition,
                'quantity_returned' => $item->quantity,
                'return_charges' => $request->return_charges ?? 0,
                'notes' => $request->notes,
            ];
        }

        $item = $this->service->updateItemOutcome($item, $request->status, $returnData);

        // Auto-mark order as delivered if not already, when item gets an outcome
        if ($order->status === 'created' || $order->status === 'shipped') {
            $this->service->updateShipmentStatus($order, 'delivered');
        }

        if ($request->wantsJson()) {
            return response()->json(['message' => "Item outcome updated to '{$request->status}'."]);
        }

        return redirect()->route('orders.show', $order)->with('success', "Product outcome updated successfully.");
    }

    public function destroy(Order $order)
    {
        $this->service->delete($order);

        if (request()->wantsJson()) {
            return response()->json(['message' => 'Order deleted.']);
        }

        return redirect()->route('orders.index')->with('success', 'Order deleted successfully.');
    }

    public function needsReminder()
    {
        $orders = $this->service->needsReminder();
        return response()->json(['data' => $orders]);
    }
}
