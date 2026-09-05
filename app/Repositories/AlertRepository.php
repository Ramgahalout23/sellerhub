<?php

namespace App\Repositories;

use App\Models\Alert;
use Illuminate\Pagination\LengthAwarePaginator;

class AlertRepository
{
    public function __construct(
        protected Alert $model = new Alert
    ) {}

    public function paginated(int $perPage = 15, ?string $type = null, ?bool $unreadOnly = null): LengthAwarePaginator
    {
        $query = $this->model->recent();

        if ($type) {
            $query->ofType($type);
        }

        if ($unreadOnly === true) {
            $query->unread();
        }

        return $query->paginate($perPage);
    }

    public function find(int $id): Alert
    {
        return $this->model->findOrFail($id);
    }

    public function create(array $data): Alert
    {
        return $this->model->create($data);
    }

    public function markAsRead(Alert $alert): Alert
    {
        $alert->markAsRead();
        return $alert->fresh();
    }

    public function markAllAsRead(): int
    {
        return $this->model->unread()->update([
            'is_read' => true,
            'read_at' => now(),
        ]);
    }

    public function unreadCount(): int
    {
        return $this->model->unread()->count();
    }
}
