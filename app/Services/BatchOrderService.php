<?php

namespace App\Services;

use App\Models\BatchOrder;
use App\Models\BatchOrderItem;
use App\Models\Product;
use App\Models\StockBatch;
use App\Repositories\BatchOrderRepository;
use App\Repositories\ProductRepository;
use Illuminate\Pagination\LengthAwarePaginator;

class BatchOrderService
{
    public function __construct(
        protected BatchOrderRepository $batchRepo,
        protected ProductRepository $productRepo
    ) {}

    public function paginated(int $perPage = 15, ?int $supplierId = null): LengthAwarePaginator
    {
        return $this->batchRepo->paginated($perPage, $supplierId);
    }

    public function find(int $id): BatchOrder
    {
        return $this->batchRepo->find($id);
    }

    public function create(array $data, array $items): BatchOrder
    {
        $batchOrder = $this->batchRepo->create($data, $items);

        // Create stock batches for each item + update product stock
        foreach ($batchOrder->items as $batchItem) {
            $product = $batchItem->product;
            if (!$product) continue;

            // Create a stock batch (FIFO tracking unit)
            StockBatch::create([
                'batch_order_item_id' => $batchItem->id,
                'product_id' => $product->id,
                'supplier_id' => $batchOrder->supplier_id,
                'original_quantity' => $batchItem->quantity,
                'remaining_quantity' => $batchItem->quantity,
                'unit_cost' => $batchItem->unit_cost,
                'status' => 'available',
            ]);

            // Update product stock
            $this->productRepo->updateStock($product, $batchItem->quantity);
        }

        // Recalculate total cost
        $batchOrder->load('items');
        $totalCost = $batchOrder->items->sum(fn($item) => $item->quantity * $item->unit_cost);
        $batchOrder->update(['total_cost' => $totalCost]);

        return $batchOrder->fresh(['supplier', 'items.product']);
    }

    public function update(BatchOrder $batchOrder, array $data, array $items = []): BatchOrder
    {
        if (!empty($items)) {
            return $this->updateItems($batchOrder, $data, $items);
        }
        return $this->batchRepo->update($batchOrder, $data);
    }

    /**
     * Update batch order header + items with stock adjustments
     */
    protected function updateItems(BatchOrder $batchOrder, array $data, array $newItems): BatchOrder
    {
        // 1. Reverse stock for ALL existing items + delete stock batches
        foreach ($batchOrder->items as $existingItem) {
            $product = $existingItem->product;
            if ($product) {
                $this->productRepo->updateStock($product, -$existingItem->quantity);
            }
            // Delete associated stock batches
            StockBatch::where('batch_order_item_id', $existingItem->id)->delete();
        }

        // 2. Delete all existing items
        $batchOrder->items()->delete();

        // 3. Update header data
        $batchOrder->update($data);

        // 4. Create new items + stock batches
        foreach ($newItems as $item) {
            if (empty($item['product_id']) || empty($item['quantity'])) continue;

            $product = Product::find($item['product_id']);
            if (!$product) continue;

            $qty = (int) $item['quantity'];
            $cost = (float) $item['unit_cost'];

            $batchItem = $batchOrder->items()->create([
                'product_id' => $product->id,
                'quantity' => $qty,
                'unit_cost' => $cost,
                'total_cost' => $qty * $cost,
            ]);

            StockBatch::create([
                'batch_order_item_id' => $batchItem->id,
                'product_id' => $product->id,
                'supplier_id' => $batchOrder->supplier_id,
                'original_quantity' => $qty,
                'remaining_quantity' => $qty,
                'unit_cost' => $cost,
                'status' => 'available',
            ]);

            $this->productRepo->updateStock($product, $qty);
        }

        // 5. Recalculate total cost
        $batchOrder->load('items');
        $totalCost = $batchOrder->items->sum(fn($item) => $item->quantity * $item->unit_cost);
        $batchOrder->update(['total_cost' => $totalCost]);

        return $batchOrder->fresh(['supplier', 'items.product']);
    }

    /**
     * Delete a single item from a batch order + reverse its stock
     */
    public function deleteItem(BatchOrder $batchOrder, $itemId): BatchOrder
    {
        $item = $batchOrder->items()->findOrFail($itemId);

        // Reverse stock for this item
        $product = $item->product;
        if ($product) {
            $this->productRepo->updateStock($product, -$item->quantity);
        }

        // Delete associated stock batch
        StockBatch::where('batch_order_item_id', $item->id)->delete();

        $item->delete();

        // Recalculate total
        $batchOrder->load('items');
        $totalCost = $batchOrder->items->sum(fn($i) => $i->quantity * $i->unit_cost);
        $batchOrder->update(['total_cost' => $totalCost]);

        return $batchOrder->fresh(['supplier', 'items.product']);
    }

    public function delete(BatchOrder $batchOrder): bool
    {
        // Reverse stock + delete stock batches
        foreach ($batchOrder->items as $item) {
            $this->productRepo->updateStock($item->product, -$item->quantity);
            StockBatch::where('batch_order_item_id', $item->id)->delete();
        }

        return $this->batchRepo->delete($batchOrder);
    }

    public function totalInvested(): float
    {
        return $this->batchRepo->totalInvested();
    }
}
