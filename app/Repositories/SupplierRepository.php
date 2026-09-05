<?php

namespace App\Repositories;

use App\Models\Supplier;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;

class SupplierRepository
{
    public function __construct(
        protected Supplier $model = new Supplier
    ) {}

    public function all(): Collection
    {
        return $this->model->orderBy('name')->get();
    }

    public function allActive(): Collection
    {
        return $this->model->active()->orderBy('name')->get();
    }

    public function paginated(int $perPage = 15, ?string $search = null): LengthAwarePaginator
    {
        $query = $this->model->orderBy('name');

        if ($search) {
            $query->search($search);
        }

        return $query->paginate($perPage);
    }

    public function find(int $id): Supplier
    {
        return $this->model->with('products')->findOrFail($id);
    }

    public function create(array $data): Supplier
    {
        return $this->model->create($data);
    }

    public function update(Supplier $supplier, array $data): Supplier
    {
        $supplier->update($data);
        return $supplier->fresh();
    }

    public function delete(Supplier $supplier): bool
    {
        return $supplier->delete();
    }

    public function count(): int
    {
        return $this->model->count();
    }

    public function countActive(): int
    {
        return $this->model->active()->count();
    }
}
