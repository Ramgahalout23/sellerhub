<?php

namespace App\Repositories;

use App\Models\Platform;
use Illuminate\Database\Eloquent\Collection;

class PlatformRepository
{
    public function __construct(
        protected Platform $model = new Platform
    ) {}

    public function allActive(): Collection
    {
        return $this->model->active()->orderBy('name')->get();
    }

    public function all(): Collection
    {
        return $this->model->orderBy('name')->get();
    }

    public function find(int $id): Platform
    {
        return $this->model->findOrFail($id);
    }

    public function findBySlug(string $slug): ?Platform
    {
        return $this->model->where('slug', $slug)->first();
    }

    public function create(array $data): Platform
    {
        return $this->model->create($data);
    }

    public function update(Platform $platform, array $data): Platform
    {
        $platform->update($data);
        return $platform->fresh();
    }

    public function delete(Platform $platform): bool
    {
        return $platform->delete();
    }

    public function count(): int
    {
        return $this->model->count();
    }
}
