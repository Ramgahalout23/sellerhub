<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreBatchOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'supplier_id' => 'required|exists:suppliers,id',
            'order_date' => 'required|date',
            'notes' => 'nullable|string|max:500',
            'items' => 'required|array|min:1',
            // Existing product fields
            'items.*.product_id' => 'nullable|exists:products,id',
            'items.*.quantity' => 'required|integer|min:1',
            'items.*.unit_cost' => 'required|numeric|min:0',
            // New product fields
            'items.*.product_name' => 'nullable|string|max:255',
            'items.*.product_sku' => 'nullable|string|max:100',
            'items.*.new_quantity' => 'nullable|integer|min:1',
            'items.*.new_unit_cost' => 'nullable|numeric|min:0',
            'items.*.selling_price' => 'nullable|numeric|min:0',
            'items.*.reorder_threshold' => 'nullable|integer|min:0',
            'items.*.product_image' => 'nullable|image|max:2048',
        ];
    }

    public function messages(): array
    {
        return [
            'items.required' => 'Please add at least one product to the batch order.',
            'items.min' => 'Please add at least one product to the batch order.',
        ];
    }
}
