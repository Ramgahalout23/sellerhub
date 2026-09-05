<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class OrderItem extends Model
{
    protected $fillable = [
        'order_id',
        'product_id',
        'quantity',
        'selling_price',
        'status',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'selling_price' => 'decimal:2',
            'quantity' => 'integer',
        ];
    }

    // --- Status Constants ---

    const STATUS_PENDING         = 'pending';
    const STATUS_SUCCESSFUL      = 'successful';
    const STATUS_CUSTOMER_RETURN = 'customer_return';
    const STATUS_RTO             = 'rto';
    const STATUS_MISSING         = 'missing';

    const OUTCOME_STATUSES = [
        self::STATUS_SUCCESSFUL,
        self::STATUS_CUSTOMER_RETURN,
        self::STATUS_RTO,
        self::STATUS_MISSING,
    ];

    const RETURN_STATUSES = [
        self::STATUS_CUSTOMER_RETURN,
        self::STATUS_RTO,
    ];

    // --- Relationships ---

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function charges(): HasMany
    {
        return $this->hasMany(OrderItemCharge::class);
    }

    public function returnDetail(): HasOne
    {
        return $this->hasOne(OrderItemReturn::class);
    }

    public function batches(): HasMany
    {
        return $this->hasMany(OrderItemBatch::class);
    }

    // --- Helpers ---

    public function getGrossRevenueAttribute(): float
    {
        return (float) $this->quantity * $this->selling_price;
    }

    public function getTotalChargesAttribute(): float
    {
        return (float) $this->charges->sum('amount');
    }

    public function getNetRevenueAttribute(): float
    {
        return $this->gross_revenue - $this->total_charges;
    }

    public function getUnitCostAttribute(): float
    {
        // Average cost from batch orders for this product
        return (float) optional($this->product)->cost_price ?? 0;
    }

    public function getProfitAttribute(): float
    {
        return $this->net_revenue - ($this->unit_cost * $this->quantity);
    }

    public function isReturnable(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    public function isOutcomeFinal(): bool
    {
        return in_array($this->status, self::OUTCOME_STATUSES);
    }
}
