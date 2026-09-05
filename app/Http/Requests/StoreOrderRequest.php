<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'order_number' => 'nullable|string|max:255',
            'platform_id' => 'required|exists:platforms,id',
            'customer_name' => 'nullable|string|max:255',
            'customer_phone' => 'nullable|string|max:20',
            'notes' => 'nullable|string',

            // Line items (required, at least 1)
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|exists:products,id',
            'items.*.quantity' => 'required|integer|min:1',
            'items.*.selling_price' => 'required|numeric|min:0',

            // Per-item charges (optional)
            'items.*.charges' => 'nullable|array',
            'items.*.charges.*.charge_name' => 'required_with:items.*.charges|string|max:255',
            'items.*.charges.*.amount' => 'required_with:items.*.charges|numeric|min:0',
        ];
    }
}
