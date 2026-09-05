<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateOrderStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'status' => 'required|in:shipped,in_transit,delivered,successful,customer_return,rto,missing',
            'return_data' => 'nullable|array',
            'return_data.return_type' => 'required_with:return_data|in:customer_return,rto,missing',
            'return_data.condition' => 'nullable|in:sellable,damaged',
            'return_data.return_charges' => 'nullable|numeric|min:0',
            'return_data.received_at' => 'nullable|date',
            'return_data.notes' => 'nullable|string',
        ];
    }
}
