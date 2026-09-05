<?php

namespace App\Services;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderItemBatch;
use App\Models\OrderItemReturn;
use App\Models\PlatformPayment;
use App\Models\ReturnBatch;
use App\Models\StockBatch;
use App\Repositories\OrderRepository;
use App\Repositories\ProductRepository;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class OrderService
{
    public function __construct(
        protected OrderRepository $orderRepo,
        protected ProductRepository $productRepo
    ) {}

    public function paginated(
        int $perPage = 15,
        ?string $status = null,
        ?int $productId = null,
        ?int $platformId = null,
        ?string $search = null
    ): LengthAwarePaginator {
        return $this->orderRepo->paginated($perPage, $status, $productId, $platformId, $search);
    }

    public function find(int $id): Order
    {
        return $this->orderRepo->find($id);
    }

    /**
     * Create order with multiple line items + FIFO batch deduction
     */
    public function create(array $data, array $items): Order
    {
        $order = $this->orderRepo->create($data, $items);

        // Deduct stock via FIFO for each line item
        foreach ($order->items as $idx => $item) {
            $selectedBatchId = $items[$idx]['stock_batch_id'] ?? null;
            $this->fifoDeduct($item, $selectedBatchId);
        }

        return $order;
    }

    /**
     * FIFO deduction: pick from oldest available batches first
     * If $selectedBatchId is provided, deduct from that specific batch.
     * Records exactly which batches supplied each sale.
     */
    protected function fifoDeduct(OrderItem $item, ?int $selectedBatchId = null): void
    {
        $productId = $item->product_id;
        $qtyNeeded = $item->quantity;
        $totalDeducted = 0;

        // If user selected a specific batch, use that one first
        if ($selectedBatchId) {
            $selectedBatch = StockBatch::where('id', $selectedBatchId)
                ->where('product_id', $productId)
                ->where('remaining_quantity', '>', 0)
                ->first();

            if ($selectedBatch) {
                $deductQty = min($qtyNeeded, $selectedBatch->remaining_quantity);
                OrderItemBatch::create([
                    'order_item_id' => $item->id,
                    'stock_batch_id' => $selectedBatch->id,
                    'quantity_deducted' => $deductQty,
                ]);
                $newRemaining = $selectedBatch->remaining_quantity - $deductQty;
                $selectedBatch->update([
                    'remaining_quantity' => $newRemaining,
                    'status' => $newRemaining <= 0 ? 'depleted' : ($newRemaining < $selectedBatch->original_quantity ? 'partial' : 'available'),
                ]);
                $this->productRepo->updateStock($item->product, -$deductQty);
                $qtyNeeded -= $deductQty;
                $totalDeducted += $deductQty;
            }
        }

        // Get available batches for this product, oldest first (FIFO)
        $batches = StockBatch::forProduct($productId)
            ->available()
            ->fifo()
            ->get();

        foreach ($batches as $batch) {
            if ($qtyNeeded <= 0) break;
            if ($batch->remaining_quantity <= 0) continue;

            $deductQty = min($qtyNeeded, $batch->remaining_quantity);

            // Record the batch-to-order-item link
            OrderItemBatch::create([
                'order_item_id' => $item->id,
                'stock_batch_id' => $batch->id,
                'quantity_deducted' => $deductQty,
            ]);

            // Reduce batch remaining
            $newRemaining = $batch->remaining_quantity - $deductQty;
            $batch->update([
                'remaining_quantity' => $newRemaining,
                'status' => $newRemaining <= 0 ? 'depleted' : ($newRemaining < $batch->original_quantity ? 'partial' : 'available'),
            ]);

            // Reduce product stock
            $this->productRepo->updateStock($item->product, -$deductQty);

            $qtyNeeded -= $deductQty;
            $totalDeducted += $deductQty;
        }

        // If we couldn't fulfill the full qty, log it but don't fail
        if ($qtyNeeded > 0) {
            \Log::warning("FIFO: Could not fully deduct {$qtyNeeded} units for OrderItem #{$item->id} (product {$productId})");
        }
    }

    /**
     * Update shipment status
     */
    public function updateShipmentStatus(Order $order, string $newStatus): Order
    {
        return $this->orderRepo->updateShipmentStatus($order, $newStatus);
    }

    /**
     * Update a single line item's outcome with batch attribution
     */
    public function updateItemOutcome(OrderItem $item, string $newOutcome, ?array $returnData = null): OrderItem
    {
        $item = $this->orderRepo->updateItemStatus($item, $newOutcome);

        // Auto-create platform payment when item is successful
        if ($newOutcome === 'successful') {
            $this->createPaymentForItem($item);
        }

        // Handle return — attribute back to source batches
        // Missing items do NOT get a return record (they're lost, not returned)
        if (in_array($newOutcome, ['customer_return', 'rto']) && $returnData) {
            $returnData['return_type'] = $newOutcome;
            $return = $this->orderRepo->addItemReturn($item, $returnData);
            $this->processReturnWithBatchAttribution($item, $return, $newOutcome, $returnData);
        }

        return $item;
    }

    /**
     * Auto-create a platform payment when an order item is marked successful
     */
    protected function createPaymentForItem(OrderItem $item): void
    {
        $revenue = $item->quantity * $item->selling_price;
        if ($revenue <= 0) return;

        PlatformPayment::create([
            'platform_id' => $item->order->platform_id,
            'amount' => $revenue,
            'type' => 'order',
            'order_id' => $item->order_id,
            'notes' => "Auto: Order #{$item->order->order_number} — {$item->product->name} x{$item->quantity}",
            'payment_date' => now()->toDateString(),
        ]);
    }

    /**
     * Process return and trace back to the originating batch(es)
     */
    protected function processReturnWithBatchAttribution(
        OrderItem $item,
        OrderItemReturn $return,
        string $returnType,
        array $returnData
    ): void {
        $product = $item->product;
        $condition = $returnData['condition'] ?? null;
        $qtyReturned = $returnData['quantity_returned'] ?? $item->quantity;

        if ($returnType === 'missing') {
            // Missing = pure loss, no stock recovery, no batch attribution needed
            return;
        }

        if ($returnType === 'rto') {
            // RTO = Return to Origin — product goes back to courier, no stock recovery
            return;
        }

        // Find which batches this order item was fulfilled from
        $sourceBatches = OrderItemBatch::where('order_item_id', $item->id)
            ->with('stockBatch')
            ->get();

        if ($condition === 'sellable' && $sourceBatches->isNotEmpty()) {
            // Add stock back to the SAME batches it came from
            $qtyToRestore = $qtyReturned;

            foreach ($sourceBatches as $sourceBatch) {
                if ($qtyToRestore <= 0) break;
                if ($sourceBatch->quantity_deducted <= 0) continue;

                $restoreQty = min($qtyToRestore, $sourceBatch->quantity_deducted);
                $batch = $sourceBatch->stockBatch;

                if ($batch) {
                    // Add stock back to the original batch
                    $newRemaining = $batch->remaining_quantity + $restoreQty;
                    $batch->update([
                        'remaining_quantity' => $newRemaining,
                        'status' => $newRemaining >= $batch->original_quantity ? 'available' : 'partial',
                    ]);

                    // Record return batch attribution
                    ReturnBatch::create([
                        'order_item_return_id' => $return->id,
                        'stock_batch_id' => $batch->id,
                        'quantity_returned' => $restoreQty,
                    ]);

                    // Restore product stock
                    $this->productRepo->updateStock($product, $restoreQty);
                }

                $qtyToRestore -= $restoreQty;
            }
        }
        // damaged = no stock recovery, no batch return
    }

    /**
     * Delete order — restore stock to original batches
     */
    public function delete(Order $order): bool
    {
        foreach ($order->items as $item) {
            if ($item->status === 'pending') {
                // Restore to original batches
                $sourceBatches = OrderItemBatch::where('order_item_id', $item->id)->get();
                foreach ($sourceBatches as $sourceBatch) {
                    $batch = $sourceBatch->stockBatch;
                    if ($batch) {
                        $newRemaining = $batch->remaining_quantity + $sourceBatch->quantity_deducted;
                        $batch->update([
                            'remaining_quantity' => $newRemaining,
                            'status' => $newRemaining >= $batch->original_quantity ? 'available' : 'partial',
                        ]);
                    }
                    $this->productRepo->updateStock($item->product, $sourceBatch->quantity_deducted);
                }
                OrderItemBatch::where('order_item_id', $item->id)->delete();
            }
        }

        return $this->orderRepo->delete($order);
    }

    public function needsReminder(): \Illuminate\Database\Eloquent\EloquentCollection
    {
        return $this->orderRepo->needsReminder();
    }

    public function getReturnStats(int $productId): array
    {
        return [
            'customer_returns' => OrderItem::where('product_id', $productId)
                ->where('status', 'customer_return')->count(),
            'rto' => OrderItem::where('product_id', $productId)
                ->where('status', 'rto')->count(),
            'missing' => OrderItem::where('product_id', $productId)
                ->where('status', 'missing')->count(),
        ];
    }

    /**
     * Supplier Comparison Report per product
     * Uses ReturnBatch and OrderItemBatch for accurate per-batch attribution
     */
    public function getSupplierComparison(int $productId): array
    {
        $batches = StockBatch::forProduct($productId)
            ->with('supplier')
            ->get()
            ->groupBy('supplier_id');

        $comparison = [];

        foreach ($batches as $supplierId => $supplierBatches) {
            $supplier = $supplierBatches->first()->supplier;
            if (!$supplier) continue;

            $batchIds = $supplierBatches->pluck('id');
            $totalSupplied = $supplierBatches->sum('original_quantity');
            $totalRemaining = $supplierBatches->sum('remaining_quantity');

            // Total deducted from this supplier's batches (actual qty sold from these batches)
            $totalDeducted = OrderItemBatch::whereIn('stock_batch_id', $batchIds)
                ->sum('quantity_deducted');

            // Successful: items where status is successful AND fulfilled from these batches
            $successfulItemIds = OrderItemBatch::whereIn('stock_batch_id', $batchIds)
                ->pluck('order_item_id')->unique();
            $successful = OrderItem::whereIn('id', $successfulItemIds)
                ->where('status', 'successful')
                ->sum('quantity');

            // Returns: use ReturnBatch for accurate attribution to specific batches
            $returns = ReturnBatch::whereIn('stock_batch_id', $batchIds)
                ->sum('quantity_returned');

            // Missing: proportionally attributed from OrderItemBatch
            $missingItemIds = OrderItemBatch::whereIn('stock_batch_id', $batchIds)
                ->pluck('order_item_id')->unique();
            $missing = OrderItem::whereIn('id', $missingItemIds)
                ->where('status', 'missing')
                ->sum('quantity');

            $totalCost = $supplierBatches->sum(fn($b) => $b->original_quantity * $b->unit_cost);
            $avgCost = $totalSupplied > 0 ? $totalCost / $totalSupplied : 0;

            // Net sold = deducted - returned (actual units that stayed sold)
            $netSold = $totalDeducted - $returns;
            // Return rate: returns as % of total supplied (not just deducted)
            $returnRate = $totalSupplied > 0 ? round(($returns / $totalSupplied) * 100, 1) : 0;

            $comparison[] = [
                'supplier' => $supplier,
                'total_supplied' => $totalSupplied,
                'total_remaining' => $totalRemaining,
                'total_sold' => $netSold,
                'successful' => $successful,
                'returns' => $returns,
                'missing' => $missing,
                'return_rate' => $returnRate,
                'total_cost' => $totalCost,
                'avg_cost_per_unit' => round($avgCost, 2),
                'batches_count' => $supplierBatches->count(),
            ];
        }

        return collect($comparison)->sortByDesc('total_supplied')->toArray();
    }
}
