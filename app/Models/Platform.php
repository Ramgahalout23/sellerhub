<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Platform extends Model
{
    protected $fillable = [
        'name',
        'slug',
        'charge_structure',
        'is_active',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'charge_structure' => 'array',
            'is_active' => 'boolean',
        ];
    }

    // --- Relationships ---

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(PlatformPayment::class);
    }

    // --- Scopes ---

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    // --- Helpers ---

    public function getChargeValue(string $key, float $default = 0): float
    {
        return (float) ($this->charge_structure[$key] ?? $default);
    }
}
