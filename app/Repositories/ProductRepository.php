<?php

namespace App\Repositories;

use App\Models\CustomField;
use App\Models\CustomFieldValue;
use App\Models\Product;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;

class ProductRepository
{
    public function __construct(
        protected Product $model = new Product
    ) {}

    public function paginated(int $perPage = 15, ?string $search = null, ?int $supplierId = null): LengthAwarePaginator
    {
        $query = $this->model->with('suppliers')->orderBy('name');

        if ($search) {
            $query->search($search);
        }

        if ($supplierId) {
            $query->whereHas('suppliers', fn ($q) => $q->where('suppliers.id', $supplierId));
        }

        return $query->paginate($perPage);
    }

    public function all(): Collection
    {
        return $this->model->active()->with('suppliers')->orderBy('name')->get();
    }

    public function allActive(): Collection
    {
        return $this->model->active()->orderBy('name')->get();
    }

    public function find(int $id): Product
    {
        return $this->model->with(['suppliers', 'batchOrderItems.batchOrder.supplier', 'orderItems.order.platform'])
            ->findOrFail($id);
    }

    public function findBySku(?string $sku): ?Product
    {
        if (! $sku) {
            return null;
        }

        return $this->model->where('sku', $sku)->first();
    }

    public function create(array $data): Product
    {
        return $this->model->create($data);
    }

    public function update(Product $product, array $data): Product
    {
        $product->update($data);

        return $product->fresh();
    }

    public function delete(Product $product): bool
    {
        return $product->delete();
    }

    public function lowStock(): Collection
    {
        return $this->model->active()->lowStock()->with('suppliers')->get();
    }

    public function count(): int
    {
        return $this->model->count();
    }

    public function countLowStock(): int
    {
        return $this->model->active()->lowStock()->count();
    }

    public function syncSuppliers(Product $product, array $supplierIds, float $lastKnownPrice): void
    {
        $syncData = [];
        foreach ($supplierIds as $supplierId) {
            $syncData[$supplierId] = ['last_known_price' => $lastKnownPrice];
        }
        $product->suppliers()->sync($syncData);
    }

    public function updateStock(Product $product, int $quantityChange): void
    {
        $product->increment('stock_quantity', $quantityChange);
    }

    // --- Custom Field Values ---

    public function getCustomFieldValues(Product $product): Collection
    {
        return CustomFieldValue::with('customField')
            ->forEntity('product', $product->id)
            ->get();
    }

    public function syncCustomFieldValues(Product $product, array $values): void
    {
        foreach ($values as $fieldId => $value) {
            CustomFieldValue::updateOrCreate(
                [
                    'custom_field_id' => $fieldId,
                    'entity_type' => 'product',
                    'entity_id' => $product->id,
                ],
                ['value' => $value]
            );
        }
    }

    public function productFields(): Collection
    {
        return CustomField::active()->forEntity('product')->ordered()->get();
    }
}
