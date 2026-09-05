<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreGeneralExpenseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'description' => 'required|string|max:255',
            'amount' => 'required|numeric|min:0',
            'category' => 'nullable|string|max:255',
            'expense_date' => 'required|date',
            'notes' => 'nullable|string',
        ];
    }
}
