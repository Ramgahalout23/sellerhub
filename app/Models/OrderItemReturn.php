<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrderItemReturn extends Model
{
    protected $fillable = [
        'order_item_id',
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

    public function orderItem(): BelongsTo
    {
        return $this->belongsTo(OrderItem::class);
    }

    public function isSellable(): bool
    {
        return $this->condition === 'sellable';
    }

    public function isDamaged(): bool
    {
        return $this->condition === 'damaged';
    }
}
