<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrderItemBatch extends Model
{
    protected $fillable = [
        'order_item_id',
        'stock_batch_id',
        'quantity_deducted',
    ];

    protected function casts(): array
    {
        return [
            'quantity_deducted' => 'integer',
        ];
    }

    public function orderItem(): BelongsTo
    {
        return $this->belongsTo(OrderItem::class);
    }

    public function stockBatch(): BelongsTo
    {
        return $this->belongsTo(StockBatch::class);
    }
}
