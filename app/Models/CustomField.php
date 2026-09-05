<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CustomField extends Model
{
    protected $fillable = [
        'name',
        'slug',
        'field_type',
        'entity_type',
        'options',
        'default_value',
        'sort_order',
        'is_required',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'options' => 'array',
            'default_value' => 'decimal:2',
            'sort_order' => 'integer',
            'is_required' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    // --- Relationships ---

    public function values(): HasMany
    {
        return $this->hasMany(CustomFieldValue::class);
    }

    // --- Scopes ---

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeForEntity($query, string $entityType)
    {
        return $query->where('entity_type', $entityType);
    }

    public function scopeOrdered($query)
    {
        return $query->orderBy('sort_order');
    }
}
