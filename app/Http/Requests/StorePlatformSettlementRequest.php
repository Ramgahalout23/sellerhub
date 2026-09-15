<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StorePlatformSettlementRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'platform_id' => 'required|exists:platforms,id',
            'period_start' => 'required|date',
            'period_end' => 'required|date|after_or_equal:period_start',
            'gross_amount' => 'required|numeric|min:0',
            'fees_amount' => 'nullable|numeric|min:0',
            // Net is derived from gross − fees when left blank, so it is optional.
            'net_amount' => 'nullable|numeric',
            'expected_on' => 'nullable|date',
            'received_on' => 'nullable|date',
            'reference' => 'nullable|string|max:255',
            'notes' => 'nullable|string|max:1000',
        ];
    }

    public function messages(): array
    {
        return [
            'period_end.after_or_equal' => 'The period end cannot be before the period start.',
        ];
    }
}
