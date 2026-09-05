<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Order extends Model
{
    protected $fillable = [
        'order_number',
        'platform_id',
        'customer_name',
        'customer_phone',
        'status',
        'shipped_at',
        'status_updated_at',
        'reminder_at',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'shipped_at' => 'datetime',
            'status_updated_at' => 'datetime',
            'reminder_at' => 'datetime',
        ];
    }

    // --- Shipment Status Constants ---

    const STATUS_CREATED    = 'created';
    const STATUS_SHIPPED    = 'shipped';
    const STATUS_IN_TRANSIT = 'in_transit';
    const STATUS_DELIVERED  = 'delivered';

    const ACTIVE_STATUSES = [
        self::STATUS_CREATED,
        self::STATUS_SHIPPED,
        self::STATUS_IN_TRANSIT,
        self::STATUS_DELIVERED,
    ];

    // --- Relationships ---

    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public function platform(): BelongsTo
    {
        return $this->belongsTo(Platform::class);
    }

    // --- Scopes ---

    public function scopeActive($query)
    {
        return $query->whereIn('status', self::ACTIVE_STATUSES);
    }

    public function scopeNeedsReminder($query)
    {
        return $query->whereNotNull('reminder_at')
                     ->where('reminder_at', '<=', now())
                     ->whereIn('status', self::ACTIVE_STATUSES);
    }

    public function scopeForPlatform($query, int $platformId)
    {
        return $query->where('platform_id', $platformId);
    }

    public function scopeDateRange($query, $from, $to)
    {
        return $query->whereBetween('created_at', [$from, $to]);
    }

    // --- Helpers ---

    /**
     * Total quantity across all line items
     */
    public function getTotalQuantityAttribute(): int
    {
        return (int) $this->items->sum('quantity');
    }

    /**
     * Total gross revenue across all line items
     */
    public function getGrossRevenueAttribute(): float
    {
        return (float) $this->items->sum(fn($item) => $item->quantity * $item->selling_price);
    }

    /**
     * Total charges across all line items
     */
    public function getTotalChargesAttribute(): float
    {
        return (float) $this->items->sum('total_charges');
    }

    /**
     * Net revenue after all charges
     */
    public function getNetRevenueAttribute(): float
    {
        return $this->gross_revenue - $this->total_charges;
    }

    /**
     * Check if all items have final outcomes
     */
    public function allItemsResolved(): bool
    {
        return $this->items->every(fn($item) => $item->isOutcomeFinal());
    }

    /**
     * Get summary of item statuses
     */
    public function getItemStatusSummary(): array
    {
        return $this->items->groupBy('status')
            ->map(fn($group) => $group->count())
            ->toArray();
    }

    /**
     * Convenience: all products in this order
     */
    public function getProductsAttribute()
    {
        return $this->items->pluck('product')->filter();
    }
}
