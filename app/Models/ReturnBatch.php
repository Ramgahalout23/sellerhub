<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReturnBatch extends Model
{
    protected $fillable = [
        'order_item_return_id',
        'stock_batch_id',
        'quantity_returned',
    ];

    protected function casts(): array
    {
        return [
            'quantity_returned' => 'integer',
        ];
    }

    public function orderItemReturn(): BelongsTo
    {
        return $this->belongsTo(OrderItemReturn::class);
    }

    public function stockBatch(): BelongsTo
    {
        return $this->belongsTo(StockBatch::class);
    }
}
