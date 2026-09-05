<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BatchOrder extends Model
{
    protected $fillable = [
        'supplier_id',
        'order_date',
        'total_cost',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'order_date' => 'date',
            'total_cost' => 'decimal:2',
        ];
    }

    // --- Relationships ---

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(BatchOrderItem::class);
    }

    // --- Scopes ---

    public function scopeForDate($query, $date)
    {
        return $query->whereDate('order_date', $date);
    }

    public function scopeForSupplier($query, int $supplierId)
    {
        return $query->where('supplier_id', $supplierId);
    }

    public function scopeRecent($query)
    {
        return $query->orderByDesc('order_date');
    }
}
