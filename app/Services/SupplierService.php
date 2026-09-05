<?php

namespace App\Services;

use App\Models\Supplier;
use App\Repositories\SupplierRepository;
use Illuminate\Pagination\LengthAwarePaginator;

class SupplierService
{
    public function __construct(
        protected SupplierRepository $repo
    ) {}

    public function paginated(int $perPage = 15, ?string $search = null): LengthAwarePaginator
    {
        return $this->repo->paginated($perPage, $search);
    }

    public function allActive(): \Illuminate\Database\Eloquent\Collection
    {
        return $this->repo->allActive();
    }

    public function find(int $id): Supplier
    {
        return $this->repo->find($id);
    }

    public function create(array $data): Supplier
    {
        return $this->repo->create($data);
    }

    public function update(Supplier $supplier, array $data): Supplier
    {
        return $this->repo->update($supplier, $data);
    }

    public function delete(Supplier $supplier): bool
    {
        return $this->repo->delete($supplier);
    }
}
