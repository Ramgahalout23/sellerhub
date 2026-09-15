<?php

namespace App\Repositories;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderItemCharge;
use App\Models\OrderItemReturn;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class OrderRepository
{
    public function __construct(
        protected Order $model = new Order
    ) {}

    public function paginated(
        int $perPage = 15,
        ?string $status = null,
        ?int $productId = null,
        ?int $platformId = null,
        ?string $search = null,
        ?string $orderStatus = null,
        ?string $paymentMode = null
    ): LengthAwarePaginator {
        $query = $this->model->with(['items.product', 'platform'])->latest();

        if ($status) {
            // Filter by item-level status
            $query->whereHas('items', fn ($q) => $q->where('status', $status));
        }

        // Order-level shipment status (including cancelled), which item status can't express.
        if ($orderStatus) {
            $query->where('status', $orderStatus);
        }

        if ($paymentMode) {
            $query->where('payment_mode', $paymentMode);
        }

        if ($productId) {
            $query->whereHas('items', fn ($q) => $q->where('product_id', $productId));
        }

        if ($platformId) {
            $query->forPlatform($platformId);
        }

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('order_number', 'like', "%{$search}%")
                    ->orWhere('customer_name', 'like', "%{$search}%");
            });
        }

        return $query->paginate($perPage);
    }

    public function find(int $id): Order
    {
        return $this->model->with(['items.product', 'items.charges', 'items.returnDetail', 'platform'])
            ->findOrFail($id);
    }

    /**
     * Create order with line items in a single transaction
     */
    public function create(array $data, array $items): Order
    {
        return DB::transaction(function () use ($data, $items) {
            $data['status_updated_at'] = now();
            $order = $this->model->create($data);

            foreach ($items as $item) {
                $orderItem = OrderItem::create([
                    'order_id' => $order->id,
                    'product_id' => $item['product_id'],
                    'quantity' => $item['quantity'],
                    'selling_price' => $item['selling_price'],
                    'status' => 'pending',
                    'notes' => $item['notes'] ?? null,
                ]);

                // Create charges for this line item
                if (! empty($item['charges'])) {
                    foreach ($item['charges'] as $charge) {
                        if (! empty($charge['charge_name']) && $charge['amount'] > 0) {
                            $orderItem->charges()->create([
                                'charge_name' => $charge['charge_name'],
                                'amount' => $charge['amount'],
                            ]);
                        }
                    }
                }
            }

            return $order->fresh(['items.product', 'platform']);
        });
    }

    /**
     * Update shipment status on the order
     */
    public function updateShipmentStatus(Order $order, string $newStatus): Order
    {
        $update = [
            'status' => $newStatus,
            'status_updated_at' => now(),
        ];

        if ($newStatus === 'shipped' && ! $order->shipped_at) {
            $update['shipped_at'] = now();
            $update['reminder_at'] = now()->addDays(7);
        }

        $order->update($update);

        return $order->fresh(['items.product', 'items.charges', 'items.returnDetail', 'platform']);
    }

    /**
     * Update a single order item's outcome status
     */
    public function updateItemStatus(OrderItem $item, string $newStatus): OrderItem
    {
        $item->update([
            'status' => $newStatus,
        ]);

        return $item->fresh(['product', 'charges', 'returnDetail']);
    }

    /**
     * Add return detail to an order item
     */
    public function addItemReturn(OrderItem $item, array $returnData): OrderItemReturn
    {
        return $item->returnDetail()->create($returnData);
    }

    public function delete(Order $order): bool
    {
        return DB::transaction(function () use ($order) {
            // Delete all items and their children
            foreach ($order->items as $item) {
                $item->charges()->delete();
                $item->returnDetail()->delete();
                $item->delete();
            }

            return $order->delete();
        });
    }

    public function needsReminder(): Collection
    {
        return $this->model->needsReminder()->with(['items.product', 'platform'])->get();
    }

    public function count(): int
    {
        return $this->model->count();
    }

    /**
     * Total successful revenue across all orders
     */
    public function totalRevenue(): float
    {
        return (float) OrderItem::where('status', 'successful')
            ->sum(DB::raw('quantity * selling_price'));
    }

    /**
     * Total charges across all order items (successful only)
     */
    public function totalCharges(): float
    {
        return (float) OrderItemCharge::whereIn('order_item_id',
            OrderItem::where('status', 'successful')->pluck('id')
        )->sum('amount');
    }
}
