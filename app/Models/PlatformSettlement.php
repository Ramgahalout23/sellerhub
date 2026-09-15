<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * One payout from a marketplace covering a period of sales.
 *
 * gross_amount is what customers paid, fees_amount what the platform kept, and
 * net_amount what should reach the bank. A settlement counts as received only once
 * received_on is set — that is what makes "money owed by Amazon" answerable, and
 * expected_on is what gives it an age.
 */
class PlatformSettlement extends Model
{
    protected $fillable = [
        'platform_id',
        'period_start',
        'period_end',
        'gross_amount',
        'fees_amount',
        'net_amount',
        'expected_on',
        'received_on',
        'reference',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'period_start' => 'date',
            'period_end' => 'date',
            'gross_amount' => 'decimal:2',
            'fees_amount' => 'decimal:2',
            'net_amount' => 'decimal:2',
            'expected_on' => 'date',
            'received_on' => 'date',
        ];
    }

    // --- Relationships ---

    public function platform(): BelongsTo
    {
        return $this->belongsTo(Platform::class);
    }

    // --- Scopes ---

    public function scopeForPlatform($query, int $platformId)
    {
        return $query->where('platform_id', $platformId);
    }

    public function scopeReceived($query)
    {
        return $query->whereNotNull('received_on');
    }

    public function scopePending($query)
    {
        return $query->whereNull('received_on');
    }

    public function scopeLatestFirst($query)
    {
        return $query->orderByDesc('period_end')->orderByDesc('id');
    }

    // --- Helpers ---

    public function isReceived(): bool
    {
        return $this->received_on !== null;
    }

    /**
     * The platform's cut as a share of what customers paid.
     */
    public function feeRate(): float
    {
        $gross = (float) $this->gross_amount;

        return $gross > 0 ? round((float) $this->fees_amount / $gross * 100, 1) : 0.0;
    }

    /**
     * The day this payout was due: the expected date, or the end of the period it covers.
     */
    public function dueOn(): ?Carbon
    {
        return $this->expected_on ?? $this->period_end;
    }

    /**
     * Days this payout has been outstanding *past its due date*.
     * Not-yet-due and already-received settlements are 0.
     */
    public function ageInDays(): int
    {
        if ($this->isReceived()) {
            return 0;
        }

        $due = $this->dueOn()?->copy()->startOfDay();

        if (! $due || $due->greaterThanOrEqualTo(Carbon::today())) {
            return 0;
        }

        return (int) abs($due->diffInDays(Carbon::today()->startOfDay()));
    }

    public function isOverdue(): bool
    {
        return $this->ageInDays() > 0;
    }

    /**
     * How urgently this needs chasing: 0 = not due, 1 = fresh, 2 = worth chasing,
     * 3 = chase now.
     */
    public function urgency(): int
    {
        $age = $this->ageInDays();

        return match (true) {
            $age <= 0 => 0,
            $age <= 15 => 1,
            $age <= 30 => 2,
            default => 3,
        };
    }
}
