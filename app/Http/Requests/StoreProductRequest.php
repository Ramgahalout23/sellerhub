<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'sku' => [
                'required',
                'string',
                'max:255',
                Rule::unique('products', 'sku')->ignore($this->route('product')),
            ],
            'description' => 'nullable|string',
            'cost_price' => 'required|numeric|min:0',
            'selling_price' => 'required|numeric|min:0',
            'stock_quantity' => 'required|integer|min:0',
            'reorder_threshold' => 'required|integer|min:0',
            'image' => 'nullable|string|max:255',
            'is_active' => 'boolean',
            'supplier_ids' => 'nullable|array',
            'supplier_ids.*' => 'exists:suppliers,id',
            'custom_field_values' => 'nullable|array',
            'custom_field_values.*' => 'nullable|string',
        ];
    }
}
