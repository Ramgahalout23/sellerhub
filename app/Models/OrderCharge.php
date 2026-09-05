<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrderCharge extends Model
{
    protected $fillable = [
        'order_id',
        'charge_name',
        'amount',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
        ];
    }

    // --- Relationships ---

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    // --- Scopes ---

    public function scopeForChargeName($query, string $chargeName)
    {
        return $query->where('charge_name', $chargeName);
    }

    public function scopeDateRange($query, $from, $to)
    {
        return $query->whereHas('order', function ($q) use ($from, $to) {
            $q->whereBetween('created_at', [$from, $to]);
        });
    }
}
