<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class StockBatch extends Model
{
    protected $fillable = [
        'batch_order_item_id',
        'product_id',
        'supplier_id',
        'original_quantity',
        'remaining_quantity',
        'unit_cost',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'original_quantity' => 'integer',
            'remaining_quantity' => 'integer',
            'unit_cost' => 'decimal:2',
        ];
    }

    // --- Relationships ---

    public function batchOrderItem(): BelongsTo
    {
        return $this->belongsTo(BatchOrderItem::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function orderItemBatches(): HasMany
    {
        return $this->hasMany(OrderItemBatch::class);
    }

    public function returnBatches(): HasMany
    {
        return $this->hasMany(ReturnBatch::class);
    }

    // --- Scopes ---

    /**
     * FIFO: oldest batches first
     */
    public function scopeFifo($query)
    {
        return $query->orderBy('created_at', 'asc')->orderBy('id', 'asc');
    }

    /**
     * Only batches with stock remaining
     */
    public function scopeAvailable($query)
    {
        return $query->where('remaining_quantity', '>', 0)->where('status', '!=', 'depleted');
    }

    /**
     * For a specific product
     */
    public function scopeForProduct($query, int $productId)
    {
        return $query->where('product_id', $productId);
    }

    // --- Helpers ---

    public function isAvailable(): bool
    {
        return $this->remaining_quantity > 0 && $this->status !== 'depleted';
    }

    public function getUtilizationPercentAttribute(): float
    {
        if ($this->original_quantity <= 0) return 0;
        return round((($this->original_quantity - $this->remaining_quantity) / $this->original_quantity) * 100, 1);
    }

    public function getTotalSoldAttribute(): int
    {
        return $this->original_quantity - $this->remaining_quantity;
    }
}
