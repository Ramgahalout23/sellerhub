<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;

class ImportOrdersRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            // Files are validated by extension below: marketplace exports are
            // routinely served as text/plain or application/vnd.ms-excel, so
            // MIME sniffing rejects perfectly good files.
            'file' => ['required', 'file', 'max:4096'],
            'platform_id' => ['nullable', 'integer', 'exists:platforms,id'],
            'dry_run' => ['nullable', 'boolean'],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator) {
                $file = $this->file('file');

                if ($file && ! in_array(strtolower($file->getClientOriginalExtension()), ['csv', 'txt', 'tsv'], true)) {
                    $validator->errors()->add('file', 'Upload a .csv or .txt export from your marketplace.');
                }
            },
        ];
    }

    public function messages(): array
    {
        return [
            'file.max' => 'Keep the file under 4 MB — split larger exports into parts.',
        ];
    }
}
