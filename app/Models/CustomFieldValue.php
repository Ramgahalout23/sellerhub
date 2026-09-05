<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CustomFieldValue extends Model
{
    protected $fillable = [
        'custom_field_id',
        'entity_type',
        'entity_id',
        'value',
    ];

    // --- Relationships ---

    public function customField(): BelongsTo
    {
        return $this->belongsTo(CustomField::class);
    }

    // --- Scopes ---

    public function scopeForEntity($query, string $entityType, int $entityId)
    {
        return $query->where('entity_type', $entityType)
                     ->where('entity_id', $entityId);
    }

    // --- Helpers ---

    public function getTypedValue()
    {
        $field = $this->customField;
        if (!$field) return $this->value;

        return match ($field->field_type) {
            'number'   => (float) $this->value,
            'boolean'  => (bool) $this->value,
            'date'     => $this->value ? \Carbon\Carbon::parse($this->value) : null,
            default    => $this->value,
        };
    }
}
