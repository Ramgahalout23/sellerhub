<?php

namespace App\Repositories;

use App\Models\BatchOrder;
use App\Models\BatchOrderItem;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class BatchOrderRepository
{
    public function __construct(
        protected BatchOrder $model = new BatchOrder
    ) {}

    public function paginated(int $perPage = 15, ?int $supplierId = null): LengthAwarePaginator
    {
        $query = $this->model->with('supplier')->recent();

        if ($supplierId) {
            $query->forSupplier($supplierId);
        }

        return $query->paginate($perPage);
    }

    public function find(int $id): BatchOrder
    {
        return $this->model->with(['supplier', 'items.product'])->findOrFail($id);
    }

    public function create(array $data, array $items): BatchOrder
    {
        return DB::transaction(function () use ($data, $items) {
            $batchOrder = $this->model->create($data);

            foreach ($items as $item) {
                BatchOrderItem::create([
                    'batch_order_id' => $batchOrder->id,
                    'product_id' => $item['product_id'],
                    'quantity' => $item['quantity'],
                    'unit_cost' => $item['unit_cost'],
                    'total_cost' => $item['quantity'] * $item['unit_cost'],
                ]);
            }

            return $batchOrder->fresh(['supplier', 'items.product']);
        });
    }

    public function update(BatchOrder $batchOrder, array $data): BatchOrder
    {
        $batchOrder->update($data);

        return $batchOrder->fresh(['supplier', 'items.product']);
    }

    public function delete(BatchOrder $batchOrder): bool
    {
        return DB::transaction(function () use ($batchOrder) {
            $batchOrder->items()->delete();

            return $batchOrder->delete();
        });
    }

    public function count(): int
    {
        return $this->model->count();
    }

    public function totalInvested(): float
    {
        return (float) $this->model->sum('total_cost');
    }
}
