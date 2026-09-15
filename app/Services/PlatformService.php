<?php

namespace App\Services;

use App\Models\Platform;
use App\Repositories\PlatformRepository;
use Illuminate\Database\Eloquent\Collection;

class PlatformService
{
    public function __construct(
        protected PlatformRepository $repo
    ) {}

    public function all(): Collection
    {
        return $this->repo->all();
    }

    public function allActive(): Collection
    {
        return $this->repo->allActive();
    }

    public function find(int $id): Platform
    {
        return $this->repo->find($id);
    }

    public function create(array $data): Platform
    {
        $data['slug'] = $data['slug'] ?? \Str::slug($data['name']);

        return $this->repo->create($data);
    }

    public function update(Platform $platform, array $data): Platform
    {
        if (isset($data['name']) && ! isset($data['slug'])) {
            $data['slug'] = \Str::slug($data['name']);
        }

        return $this->repo->update($platform, $data);
    }

    public function delete(Platform $platform): bool
    {
        return $this->repo->delete($platform);
    }
}
