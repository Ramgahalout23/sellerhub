<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReturnDetail extends Model
{
    protected $fillable = [
        'order_id',
        'return_type',
        'condition',
        'quantity_returned',
        'return_charges',
        'notes',
        'received_at',
    ];

    protected function casts(): array
    {
        return [
            'quantity_returned' => 'integer',
            'return_charges' => 'decimal:2',
            'received_at' => 'datetime',
        ];
    }

    // --- Status Constants ---

    const TYPE_CUSTOMER_RETURN = 'customer_return';
    const TYPE_RTO             = 'rto';
    const TYPE_MISSING         = 'missing';

    const CONDITION_SELLABLE = 'sellable';
    const CONDITION_DAMAGED  = 'damaged';

    // --- Relationships ---

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    // --- Scopes ---

    public function scopeCustomerReturns($query)
    {
        return $query->where('return_type', self::TYPE_CUSTOMER_RETURN);
    }

    public function scopeRTOs($query)
    {
        return $query->where('return_type', self::TYPE_RTO);
    }

    public function scopeMissing($query)
    {
        return $query->where('return_type', self::TYPE_MISSING);
    }

    public function scopeSellable($query)
    {
        return $query->where('condition', self::CONDITION_SELLABLE);
    }

    public function scopeDamaged($query)
    {
        return $query->where('condition', self::CONDITION_DAMAGED);
    }

    // --- Helpers ---

    public function isSellable(): bool
    {
        return $this->condition === self::CONDITION_SELLABLE;
    }

    public function isDamaged(): bool
    {
        return $this->condition === self::CONDITION_DAMAGED;
    }
}
