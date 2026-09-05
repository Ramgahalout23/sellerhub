@extends('layouts.app')
@section('title', 'Edit Batch Order #' . $batchOrder->id)
@section('subtitle', 'Update purchase details and items')

@section('content')
<div class="max-w-4xl space-y-6">

    <div class="flex flex-wrap items-center justify-between gap-2">
        <div>
            <h2 class="text-lg font-bold text-gray-900">Edit Batch Order #{{ $batchOrder->id }}</h2>
            <p class="text-sm text-gray-500">Stock will be adjusted automatically when items change</p>
        </div>
        <a href="{{ route('batch-orders.show', $batchOrder) }}" class="text-sm font-medium text-gray-500 hover:text-gray-700 transition-colors">
            <i class="bi bi-arrow-left mr-1"></i> Back to Order
        </a>
    </div>

    <form action="{{ route('batch-orders.update', $batchOrder) }}" method="POST" id="editForm" class="pb-44 lg:pb-0">
        @csrf
        @method('PUT')

        {{-- Header --}}
        <x-card>
            <div class="grid grid-cols-1 gap-5 sm:grid-cols-2">
                <div>
                    <label class="block text-xs font-bold uppercase tracking-wider text-gray-500 mb-2">Supplier *</label>
                    <select name="supplier_id" class="w-full rounded-xl border border-gray-200 bg-gray-50 px-4 py-2.5 text-sm focus:border-brand-500 focus:ring-2 focus:ring-brand-500/20 focus:outline-none transition-all" required>
                        @foreach($suppliers as $s)
                            <option value="{{ $s->id }}" {{ $batchOrder->supplier_id == $s->id ? 'selected' : '' }}>{{ $s->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-bold uppercase tracking-wider text-gray-500 mb-2">Order Date *</label>
                    <input type="date" name="order_date" class="w-full rounded-xl border border-gray-200 bg-gray-50 px-4 py-2.5 text-sm focus:border-brand-500 focus:ring-2 focus:ring-brand-500/20 focus:outline-none transition-all" value="{{ $batchOrder->order_date->format('Y-m-d') }}" required>
                </div>
            </div>
            <div class="mt-4">
                <label class="block text-xs font-bold uppercase tracking-wider text-gray-500 mb-2">Notes</label>
                <input type="text" name="notes" class="w-full rounded-xl border border-gray-200 bg-gray-50 px-4 py-2.5 text-sm focus:border-brand-500 focus:ring-2 focus:ring-brand-500/20 focus:outline-none transition-all" value="{{ old('notes', $batchOrder->notes) }}" placeholder="e.g. Monthly restock, emergency purchase">
            </div>
        </x-card>

        {{-- Items --}}
        <x-card title="Items" subtitle="Edit products, quantities, or costs — stock adjusts automatically">
            <div id="itemsContainer" class="space-y-4">
                @foreach($batchOrder->items as $idx => $item)
                <div class="item-row rounded-xl border border-gray-200 bg-gray-50/50 p-4 relative" data-index="{{ $idx }}" data-item-id="{{ $item->id }}">
                    <div class="flex items-center justify-between mb-3">
                        <span class="text-xs font-bold text-gray-400">Item #{{ $idx + 1 }}</span>
                        <div class="flex items-center gap-2">
                            @if($item->quantity > 0)
                            <span class="text-[10px] text-amber-600 bg-amber-50 rounded-md px-2 py-0.5">Stock: {{ $item->product->stock_quantity }} units</span>
                            @endif
                            <button type="button" class="remove-item rounded-lg p-1 text-gray-400 hover:text-rose-600 hover:bg-rose-50 transition-all" title="Remove this item">
                                <i class="bi bi-trash text-sm"></i>
                            </button>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="block text-[10px] font-bold uppercase tracking-wider text-gray-400 mb-1">Product *</label>
                        <select name="items[{{ $idx }}][product_id]" class="w-full rounded-lg border border-gray-200 bg-white px-3 py-2.5 text-sm focus:border-brand-500 focus:ring-1 focus:ring-brand-500 focus:outline-none transition-all product-select" required>
                            <option value="">Select product...</option>
                            @foreach($products as $p)
                                <option value="{{ $p->id }}" {{ $item->product_id == $p->id ? 'selected' : '' }}>{{ $p->name }} ({{ $p->sku }})</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block text-[10px] font-bold uppercase tracking-wider text-gray-400 mb-1">Qty *</label>
                            <input type="number" name="items[{{ $idx }}][quantity]" class="item-qty w-full rounded-lg border border-gray-200 bg-white px-3 py-2.5 text-sm focus:border-brand-500 focus:ring-1 focus:ring-brand-500 focus:outline-none transition-all" value="{{ $item->quantity }}" min="1" required>
                        </div>
                        <div>
                            <label class="block text-[10px] font-bold uppercase tracking-wider text-gray-400 mb-1">Cost (₹) *</label>
                            <input type="number" name="items[{{ $idx }}][unit_cost]" class="item-cost w-full rounded-lg border border-gray-200 bg-white px-3 py-2.5 text-sm focus:border-brand-500 focus:ring-1 focus:ring-brand-500 focus:outline-none transition-all" value="{{ $item->unit_cost }}" step="0.01" min="0" required>
                        </div>
                    </div>
                    <div class="mt-2 flex justify-end">
                        <span class="text-sm font-bold text-gray-900 item-total">₹{{ number_format($item->total_cost) }}</span>
                    </div>
                </div>
                @endforeach
            </div>

            {{-- Add Product Button --}}
            <div class="pt-4 border-t border-gray-100">
                <button type="button" id="addItem" class="w-full inline-flex items-center justify-center gap-2 rounded-xl border-2 border-dashed border-brand-300 bg-brand-50/50 px-4 py-3 text-sm font-semibold text-brand-600 hover:border-brand-400 hover:bg-brand-100 transition-all">
                    <i class="bi bi-plus-circle text-base"></i> Add Another Product
                </button>
            </div>
        </x-card>

        {{-- Total + Submit (fixed above bottom nav) --}}
        <div class="fixed bottom-16 left-0 right-0 z-40 lg:static lg:bottom-auto border-t border-gray-100">
            <div class="rounded-t-xl lg:rounded-xl border border-gray-200 bg-white shadow-sm lg:shadow-sm shadow-[0_-2px_10px_rgba(0,0,0,0.06)]">
                <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 p-4 lg:p-5">
                    <div>
                        <p class="text-[10px] font-bold uppercase tracking-wider text-gray-400 mb-1">TOTAL COST</p>
                        <p class="text-2xl font-bold text-gray-900" id="grandTotal">₹{{ number_format($batchOrder->total_cost) }}</p>
                        <p class="text-xs text-gray-400">Stock adjustments are automatic</p>
                    </div>
                    <div class="flex items-center gap-3 sm:shrink-0">
                        <a href="{{ route('batch-orders.show', $batchOrder) }}" class="text-sm font-medium text-gray-500 hover:text-gray-700 transition-colors">Cancel</a>
                        <button type="submit" class="touch-target rounded-xl bg-brand-600 px-6 py-3 text-sm font-semibold text-white shadow-lg shadow-brand-200 hover:bg-brand-700 transition-all">
                            <i class="bi bi-check-lg mr-1"></i> Save Changes
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </form>
</div>
@endsection

@section('scripts')
<script>
@php
    $productsJson = $products->map(fn($p) => ['id' => $p->id, 'name' => $p->name, 'sku' => $p->sku, 'cost_price' => $p->cost_price])->values();
@endphp
const products = @json($productsJson);
let itemIndex = {{ $batchOrder->items->count() }};

// Add new empty item row
document.getElementById('addItem').addEventListener('click', function() {
    const container = document.getElementById('itemsContainer');
    const productOptions = products.map(p => `<option value="${p.id}">${p.name} (${p.sku})</option>`).join('');

    const div = document.createElement('div');
    div.className = 'item-row rounded-xl border border-gray-200 bg-gray-50/50 p-4 relative';
    div.dataset.index = itemIndex;
    div.innerHTML = `
        <div class="flex items-center justify-between mb-3">
            <span class="text-xs font-bold text-gray-400">New Item</span>
            <button type="button" class="remove-item rounded-lg p-1 text-gray-400 hover:text-rose-600 hover:bg-rose-50 transition-all" title="Remove">
                <i class="bi bi-trash text-sm"></i>
            </button>
        </div>
        <div class="mb-3">
            <label class="block text-[10px] font-bold uppercase tracking-wider text-gray-400 mb-1">Product *</label>
            <select name="items[${itemIndex}][product_id]" class="w-full rounded-lg border border-gray-200 bg-white px-3 py-2.5 text-sm focus:border-brand-500 focus:ring-1 focus:ring-brand-500 focus:outline-none transition-all product-select" required>
                <option value="">Select product...</option>
                ${productOptions}
            </select>
        </div>
        <div class="grid grid-cols-2 gap-3">
            <div>
                <label class="block text-[10px] font-bold uppercase tracking-wider text-gray-400 mb-1">Qty *</label>
                <input type="number" name="items[${itemIndex}][quantity]" class="item-qty w-full rounded-lg border border-gray-200 bg-white px-3 py-2.5 text-sm focus:border-brand-500 focus:ring-1 focus:ring-brand-500 focus:outline-none transition-all" value="1" min="1" required>
            </div>
            <div>
                <label class="block text-[10px] font-bold uppercase tracking-wider text-gray-400 mb-1">Cost (₹) *</label>
                <input type="number" name="items[${itemIndex}][unit_cost]" class="item-cost w-full rounded-lg border border-gray-200 bg-white px-3 py-2.5 text-sm focus:border-brand-500 focus:ring-1 focus:ring-brand-500 focus:outline-none transition-all" placeholder="₹0.00" step="0.01" min="0" required>
            </div>
        </div>
        <div class="mt-2 flex justify-end">
            <span class="text-sm font-bold text-gray-900 item-total">₹0</span>
        </div>`;

    container.appendChild(div);
    itemIndex++;
    bindEvents(div);
    calculateTotal();
});

// Bind events on a row
function bindEvents(row) {
    row.querySelector('.remove-item')?.addEventListener('click', function() {
        if (document.querySelectorAll('.item-row').length > 1) {
            row.style.opacity = '0.5';
            row.style.textDecoration = 'line-through';
            setTimeout(() => {
                row.remove();
                calculateTotal();
            }, 200);
        }
    });

    row.querySelectorAll('.item-qty, .item-cost').forEach(inp => {
        inp.addEventListener('input', function() {
            const qty = parseFloat(row.querySelector('.item-qty')?.value) || 0;
            const cost = parseFloat(row.querySelector('.item-cost')?.value) || 0;
            row.querySelector('.item-total').textContent = '₹' + (qty * cost).toLocaleString(undefined, {minimumFractionDigits: 0, maximumFractionDigits: 2});
            calculateTotal();
        });
    });
}

function calculateTotal() {
    let total = 0;
    document.querySelectorAll('.item-row').forEach(row => {
        const qty = parseFloat(row.querySelector('.item-qty')?.value) || 0;
        const cost = parseFloat(row.querySelector('.item-cost')?.value) || 0;
        total += qty * cost;
    });
    document.getElementById('grandTotal').textContent = '₹' + total.toLocaleString(undefined, {minimumFractionDigits: 0, maximumFractionDigits: 2});
}

// Init
document.querySelectorAll('.item-row').forEach(row => bindEvents(row));
</script>
@endsection
