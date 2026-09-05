<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class GeneralExpense extends Model
{
    protected $fillable = [
        'description',
        'amount',
        'category',
        'expense_date',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'expense_date' => 'date',
        ];
    }

    // --- Scopes ---

    public function scopeForCategory($query, string $category)
    {
        return $query->where('category', $category);
    }

    public function scopeDateRange($query, $from, $to)
    {
        return $query->whereBetween('expense_date', [$from, $to]);
    }

    public function scopeRecent($query)
    {
        return $query->orderByDesc('expense_date');
    }
}
