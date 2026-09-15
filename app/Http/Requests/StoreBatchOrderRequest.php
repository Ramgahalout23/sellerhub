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
            // Supplier invoices (photos or PDFs) attached to this purchase.
            'invoices' => 'nullable|array|max:20',
            'invoices.*' => 'file|mimes:pdf,jpg,jpeg,png,webp|max:8192',
            'items' => 'required|array|min:1',
            // A row is either an existing product (product_id + quantity/unit_cost)
            // or a brand new one (product_name/sku + new_quantity/new_unit_cost).
            'items.*.product_id' => 'nullable|exists:products,id',
            'items.*.quantity' => ['nullable', 'required_with:items.*.product_id', 'integer', 'min:1'],
            'items.*.unit_cost' => ['nullable', 'required_with:items.*.product_id', 'numeric', 'min:0'],
            // New product fields
            'items.*.product_name' => ['nullable', 'required_without:items.*.product_id', 'string', 'max:255'],
            'items.*.product_sku' => ['nullable', 'required_without:items.*.product_id', 'string', 'max:100'],
            'items.*.new_quantity' => ['nullable', 'required_without:items.*.product_id', 'integer', 'min:1'],
            'items.*.new_unit_cost' => ['nullable', 'required_without:items.*.product_id', 'numeric', 'min:0'],
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
            'items.*.quantity.required_with' => 'Quantity is required for an existing product.',
            'items.*.unit_cost.required_with' => 'Unit cost is required for an existing product.',
            'items.*.product_name.required_without' => 'Product name is required for a new product.',
            'items.*.product_sku.required_without' => 'SKU is required for a new product.',
            'items.*.new_quantity.required_without' => 'Quantity is required for a new product.',
            'items.*.new_unit_cost.required_without' => 'Unit cost is required for a new product.',
            'invoices.array' => 'Invoices must be uploaded as a list of files.',
            'invoices.max' => 'You may attach at most 20 invoice files per purchase.',
            'invoices.*.mimes' => 'Each invoice must be a PDF or an image (jpg, png, webp).',
            'invoices.*.max' => 'Each invoice may not be larger than 8 MB.',
        ];
    }
}
