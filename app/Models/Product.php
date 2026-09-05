<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;use Illuminate\Support\Facades\DB;

class Product extends Model
{
    protected $fillable = [
        'name',
        'sku',
        'description',
        'cost_price',
        'selling_price',
        'stock_quantity',
        'reorder_threshold',
        'image',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'cost_price' => 'decimal:2',
            'selling_price' => 'decimal:2',
            'is_active' => 'boolean',
        ];
    }

    // --- Relationships ---

    public function suppliers(): BelongsToMany
    {
        return $this->belongsToMany(Supplier::class)
            ->withPivot('last_known_price')
            ->withTimestamps();
    }

    public function batchOrderItems(): HasMany
    {
        return $this->hasMany(BatchOrderItem::class);
    }

    /**
     * Each line item where this product appears (across all orders)
     */
    public function orderItems(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public function customFieldValues(): MorphMany
    {
        return $this->morphMany(CustomFieldValue::class, 'entity');
    }

    // --- Scopes ---

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeLowStock($query)
    {
        return $query->whereColumn('stock_quantity', '<=', 'reorder_threshold');
    }

    public function scopeSearch($query, string $term)
    {
        return $query->where(function ($q) use ($term) {
            $q->where('name', 'like', "%{$term}%")
              ->orWhere('sku', 'like', "%{$term}%");
        });
    }

    // --- Helpers ---

    public function isLowStock(): bool
    {
        return $this->stock_quantity <= $this->reorder_threshold;
    }

    public function getTotalInvestedAttribute(): float
    {
        return (float) $this->batchOrderItems->sum('total_cost');
    }

    /**
     * Units sold (only successful outcomes across all order items)
     */
    public function getTotalSoldAttribute(): int
    {
        return (int) $this->orderItems()
            ->where('status', 'successful')
            ->sum('quantity');
    }

    /**
     * Units returned — uses actual return records, not order item quantity
     * (an order might have qty=35 but only 10 returned)
     */
    public function getTotalReturnedAttribute(): int
    {
        $returnableItemIds = $this->orderItems()
            ->whereIn('status', ['customer_return', 'rto'])
            ->pluck('id');

        return (int) OrderItemReturn::whereIn('order_item_id', $returnableItemIds)
            ->sum('quantity_returned');
    }

    /**
     * Units missing
     */
    public function getTotalMissingAttribute(): int
    {
        return (int) $this->orderItems()
            ->where('status', 'missing')
            ->sum('quantity');
    }

    public function getReturnRatioAttribute(): float
    {
        $dispatched = $this->total_sold + $this->total_returned + $this->total_missing;
        if ($dispatched === 0) return 0;
        return round(($this->total_returned / $dispatched) * 100, 1);
    }

    /**
     * Total revenue received from successful sales
     */
    public function getTotalReceivedAttribute(): float
    {
        return (float) $this->orderItems()
            ->where('status', 'successful')
            ->sum(DB::raw('quantity * selling_price'));
    }

    /**
     * Total charges across all order items for this product (successful only)
     */
    public function getTotalChargesAttribute(): float
    {
        $itemIds = $this->orderItems()->where('status', 'successful')->pluck('id');
        return (float) OrderItemCharge::whereIn('order_item_id', $itemIds)->sum('amount');
    }
}
