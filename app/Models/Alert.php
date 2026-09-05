<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Alert extends Model
{
    protected $fillable = [
        'type',
        'title',
        'message',
        'entity_type',
        'entity_id',
        'is_read',
        'read_at',
    ];

    protected function casts(): array
    {
        return [
            'is_read' => 'boolean',
            'read_at' => 'datetime',
        ];
    }

    // --- Alert Types ---

    const TYPE_RETURN_REMINDER = 'return_reminder';
    const TYPE_LOW_STOCK       = 'low_stock';
    const TYPE_GENERAL         = 'general';

    // --- Scopes ---

    public function scopeUnread($query)
    {
        return $query->where('is_read', false);
    }

    public function scopeOfType($query, string $type)
    {
        return $query->where('type', $type);
    }

    public function scopeRecent($query)
    {
        return $query->orderByDesc('created_at');
    }

    // --- Helpers ---

    public function markAsRead(): void
    {
        $this->update([
            'is_read' => true,
            'read_at' => now(),
        ]);
    }
}
