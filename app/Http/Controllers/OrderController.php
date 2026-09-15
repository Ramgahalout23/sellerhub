<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreOrderRequest;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Platform;
use App\Services\OrderService;
use App\Services\PlatformChargeCalculator;
use App\Services\PlatformService;
use App\Services\ProductService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OrderController extends Controller
{
    public function __construct(
        protected OrderService $service,
        protected ProductService $productService,
        protected PlatformService $platformService,
        protected PlatformChargeCalculator $charges
    ) {}

    public function index()
    {
        $search = request('search');
        $status = request('status');
        $productId = request('product_id');
        $platformId = request('platform_id');

        $orderStatus = request('order_status');
        $paymentMode = request('payment_mode');

        $orders = $this->service->paginated(15, $status, $productId, $platformId, $search, $orderStatus, $paymentMode);
        $products = $this->productService->allActive();
        $platforms = $this->platformService->allActive();

        return view('orders.index', compact('orders', 'products', 'platforms'));
    }

    public function create()
    {
        $products = $this->productService->allActive();
        $platforms = $this->platformService->allActive();

        // Describe each platform's fees up front so the dropdown is informative
        // without an extra request when the form loads.
        $platformFeeNotes = $platforms->mapWithKeys(
            fn (Platform $platform) => [$platform->id => $this->charges->describe($platform)]
        );

        return view('orders.create', compact('products', 'platforms', 'platformFeeNotes'));
    }

    /**
     * Preview the charges a platform would deduct for a given line amount.
     * Powers the order form's auto-filled charges (and is reused by the importer).
     */
    public function chargePreview(Request $request): JsonResponse
    {
        $data = $request->validate([
            'platform_id' => 'required|integer|exists:platforms,id',
            'amount' => 'required|numeric|min:0',
        ]);

        $platform = $this->platformService->find((int) $data['platform_id']);

        return response()->json($this->charges->forAmount($platform, (float) $data['amount']));
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

        return redirect()->route('orders.show', $order)->with('success', 'Order created successfully with '.$order->items->count().' item(s).');
    }

    public function show(Order $order)
    {
        $order->load(['items.product', 'items.charges', 'items.returnDetail', 'platform']);

        return view('orders.show', compact('order'));
    }

    /**
     * Update the shipment status of the whole order
     */
    public function updateShipmentStatus(Request $request, Order $order)
    {
        if ($cancelled = $this->refuseWhenCancelled($order, $request)) {
            return $cancelled;
        }

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
    public function updateItemStatus(Request $request, Order $order, OrderItem $item)
    {
        if ($cancelled = $this->refuseWhenCancelled($order, $request)) {
            return $cancelled;
        }

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

        return redirect()->route('orders.show', $order)->with('success', 'Product outcome updated successfully.');
    }

    /**
     * Cancel an order: return its stock, keep it in history, stop its reminders.
     */
    public function cancel(Request $request, Order $order)
    {
        if ($cancelled = $this->refuseWhenCancelled($order, $request)) {
            return $cancelled;
        }

        $this->service->cancel($order);

        if ($request->wantsJson()) {
            return response()->json(['message' => 'Order cancelled and stock returned.']);
        }

        return redirect()->route('orders.show', $order)
            ->with('success', 'Order cancelled — its stock is back in the batches it came from.');
    }

    /**
     * A cancelled order must not be shipped, delivered or have outcomes applied: its
     * stock was already handed back, so any of those would double-count.
     */
    protected function refuseWhenCancelled(Order $order, Request $request)
    {
        if ($order->status !== Order::STATUS_CANCELLED) {
            return null;
        }

        $message = 'Order '.($order->order_number ?? '#'.$order->id).' was cancelled — this sale is closed.';

        return $request->wantsJson()
            ? response()->json(['message' => $message], 422)
            : back()->with('error', $message);
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
