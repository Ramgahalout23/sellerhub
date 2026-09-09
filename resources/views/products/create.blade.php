@extends('layouts.app')
@section('title', 'Add Product')

@section('content')
<div class="max-w-4xl">
    <x-card>
        <form action="{{ route('products.store') }}" method="POST" class="space-y-6 pb-32 lg:pb-0">
            @csrf
            <div class="grid grid-cols-1 gap-5 sm:grid-cols-2">
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-1.5">Product Name *</label>
                    <input type="text" name="name" class="w-full rounded-xl border border-gray-300 bg-white px-4 py-2.5 text-sm focus:border-brand-500 focus:ring-2 focus:ring-brand-500/20 focus:outline-none" value="{{ old('name') }}" required>
                </div>
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-1.5">SKU *</label>
                    <input type="text" name="sku" class="w-full rounded-xl border border-gray-300 bg-white px-4 py-2.5 text-sm focus:border-brand-500 focus:ring-2 focus:ring-brand-500/20 focus:outline-none" value="{{ old('sku') }}" required placeholder="e.g. ELEC-TWS-001">
                </div>
            </div>
            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-1.5">Description</label>
                <textarea name="description" class="w-full rounded-xl border border-gray-300 bg-white px-4 py-2.5 text-sm focus:border-brand-500 focus:ring-2 focus:ring-brand-500/20 focus:outline-none" rows="2">{{ old('description') }}</textarea>
            </div>
            <div class="grid grid-cols-2 gap-5 sm:grid-cols-4">
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-1.5">Cost Price (₹) *</label>
                    <input type="number" name="cost_price" class="w-full rounded-xl border border-gray-300 bg-white px-4 py-2.5 text-sm focus:border-brand-500 focus:ring-2 focus:ring-brand-500/20 focus:outline-none" value="{{ old('cost_price') }}" step="0.01" required>
                </div>
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-1.5">Selling Price (₹) *</label>
                    <input type="number" name="selling_price" class="w-full rounded-xl border border-gray-300 bg-white px-4 py-2.5 text-sm focus:border-brand-500 focus:ring-2 focus:ring-brand-500/20 focus:outline-none" value="{{ old('selling_price') }}" step="0.01" required>
                </div>
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-1.5">Stock *</label>
                    <input type="number" name="stock_quantity" class="w-full rounded-xl border border-gray-300 bg-white px-4 py-2.5 text-sm focus:border-brand-500 focus:ring-2 focus:ring-brand-500/20 focus:outline-none" value="{{ old('stock_quantity', 0) }}" required>
                </div>
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-1.5">Reorder At *</label>
                    <input type="number" name="reorder_threshold" class="w-full rounded-xl border border-gray-300 bg-white px-4 py-2.5 text-sm focus:border-brand-500 focus:ring-2 focus:ring-brand-500/20 focus:outline-none" value="{{ old('reorder_threshold', 5) }}" required>
                    <p class="text-[10px] text-gray-400 mt-1">Alert when stock falls below this</p>
                </div>
            </div>
            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-2">Suppliers</label>
                <div class="flex flex-wrap gap-3">
                    @foreach($suppliers as $supplier)
                        <label class="flex items-center gap-2 rounded-lg border border-gray-200 bg-gray-50 px-3 py-2 text-sm cursor-pointer hover:bg-gray-100 transition-colors">
                            <input type="checkbox" name="supplier_ids[]" value="{{ $supplier->id }}" class="h-4 w-4 rounded border-gray-300 text-brand-600 focus:ring-brand-500" {{ in_array($supplier->id, old('supplier_ids', [])) ? 'checked' : '' }}>
                            {{ $supplier->name }}
                        </label>
                    @endforeach
                </div>
            </div>
            @if($customFields->isNotEmpty())
            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-2">Custom Fields</label>
                <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                    @foreach($customFields as $field)
                    <div>
                        <label class="block text-xs font-medium text-gray-500 mb-1">{{ $field->name }}</label>
                        <input type="{{ $field->field_type === 'number' ? 'number' : 'text' }}" name="custom_field_values[{{ $field->id }}]" class="w-full rounded-xl border border-gray-200 bg-gray-50 px-3 py-2 text-sm focus:border-brand-500 focus:ring-1 focus:ring-brand-500 focus:outline-none" value="{{ old("custom_field_values.{$field->id}") }}" step="{{ $field->field_type === 'number' ? '0.01' : '' }}">
                    </div>
                    @endforeach
                </div>
            </div>
            @endif
            <div class="lg:static fixed bottom-16 left-0 right-0 lg:bottom-auto lg:left-auto lg:right-auto z-40 lg:z-auto bg-white lg:bg-transparent">
                <div class="flex items-center gap-3 border-t border-gray-100 pt-5 pb-4 lg:pb-5 px-4 lg:px-0 shadow-[0_-2px_10px_rgba(0,0,0,0.06)] lg:shadow-none">
                    <button type="submit" class="touch-target rounded-xl bg-brand-600 px-5 py-2.5 text-sm font-semibold text-white shadow-lg shadow-brand-200 hover:bg-brand-700 transition-all">Create Product</button>
                    <a href="{{ route('products.index') }}" class="text-sm font-medium text-gray-500 hover:text-gray-700 transition-colors">Cancel</a>
                </div>
            </div>
        </form>
    </x-card>
</div>
@endsection
