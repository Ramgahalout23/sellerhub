<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PlatformPayment extends Model
{
    protected $fillable = [
        'platform_id',
        'order_id',
        'type',
        'amount',
        'payment_date',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'payment_date' => 'date',
        ];
    }

    // --- Constants ---

    const TYPE_ORDER = 'order';   // Auto-created from successful order

    const TYPE_MANUAL = 'manual';  // Manual platform settlement

    // --- Relationships ---

    public function platform(): BelongsTo
    {
        return $this->belongsTo(Platform::class);
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    // --- Scopes ---

    public function scopeForPlatform($query, int $platformId)
    {
        return $query->where('platform_id', $platformId);
    }

    public function scopeDateRange($query, $from, $to)
    {
        return $query->whereBetween('payment_date', [$from, $to]);
    }

    public function scopeRecent($query)
    {
        return $query->orderByDesc('payment_date');
    }

    public function scopeFromOrders($query)
    {
        return $query->where('type', self::TYPE_ORDER);
    }

    public function scopeManual($query)
    {
        return $query->where('type', self::TYPE_MANUAL);
    }

    // --- Helpers ---

    public function isFromOrder(): bool
    {
        return $this->type === self::TYPE_ORDER;
    }

    public function isManual(): bool
    {
        return $this->type === self::TYPE_MANUAL;
    }
}
