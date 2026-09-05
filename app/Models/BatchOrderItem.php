<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BatchOrderItem extends Model
{
    protected $fillable = [
        'batch_order_id',
        'product_id',
        'quantity',
        'unit_cost',
        'total_cost',
    ];

    protected function casts(): array
    {
        return [
            'unit_cost' => 'decimal:2',
            'total_cost' => 'decimal:2',
            'quantity' => 'integer',
        ];
    }

    // --- Relationships ---

    public function batchOrder(): BelongsTo
    {
        return $this->belongsTo(BatchOrder::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
