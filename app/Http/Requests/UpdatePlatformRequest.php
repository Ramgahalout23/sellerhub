<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdatePlatformRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => 'sometimes|required|string|max:255',
            'slug' => 'nullable|string|max:255|unique:platforms,slug,'.$this->route('platform'),
            'charge_structure' => 'nullable|array',
            'charge_structure.*' => 'numeric',
            'is_active' => 'boolean',
            'notes' => 'nullable|string',
        ];
    }
}
