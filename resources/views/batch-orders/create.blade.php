@extends('layouts.app')
@section('title', 'New Batch Order')
@section('subtitle', 'Purchase products from a supplier')

@section('content')
<div class="max-w-4xl space-y-6">

    {{-- Header --}}
    <div class="flex flex-wrap items-center justify-between gap-2">
        <div>
            <h2 class="text-lg font-bold text-gray-900">Create Batch Order</h2>
            <p class="text-sm text-gray-500">Add products purchased from a supplier</p>
        </div>
        <a href="{{ route('batch-orders.index') }}" class="text-sm font-medium text-gray-500 hover:text-gray-700 transition-colors">
            <i class="bi bi-arrow-left mr-1"></i> Back to Batch Orders
        </a>
    </div>

    <form action="{{ route('batch-orders.store') }}" method="POST" id="batchForm" class="pb-44 lg:pb-0">
        @csrf

        {{-- Supplier & Date --}}
        <x-card>
            <div class="grid grid-cols-1 gap-5 sm:grid-cols-2">
                <div>
                    <label class="block text-xs font-bold uppercase tracking-wider text-gray-500 mb-2">Supplier *</label>
                    <select name="supplier_id" id="supplierSelect" class="w-full rounded-xl border border-gray-200 bg-gray-50 px-4 py-2.5 text-sm focus:border-brand-500 focus:ring-2 focus:ring-brand-500/20 focus:outline-none transition-all" required>
                        <option value="">Select supplier...</option>
                        @foreach($suppliers as $s)
                            <option value="{{ $s->id }}" data-supplier-id="{{ $s->id }}" {{ $selectedSupplierId == $s->id ? 'selected' : '' }}>{{ $s->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-bold uppercase tracking-wider text-gray-500 mb-2">Order Date *</label>
                    <input type="date" name="order_date" class="w-full rounded-xl border border-gray-200 bg-gray-50 px-4 py-2.5 text-sm focus:border-brand-500 focus:ring-2 focus:ring-brand-500/20 focus:outline-none transition-all" value="{{ old('order_date', date('Y-m-d')) }}" required>
                </div>
            </div>
            <div class="mt-4">
                <label class="block text-xs font-bold uppercase tracking-wider text-gray-500 mb-2">Notes</label>
                <input type="text" name="notes" class="w-full rounded-xl border border-gray-200 bg-gray-50 px-4 py-2.5 text-sm focus:border-brand-500 focus:ring-2 focus:ring-brand-500/20 focus:outline-none transition-all" value="{{ old('notes') }}" placeholder="e.g. Monthly restock, emergency purchase">
            </div>
        </x-card>

        {{-- Products --}}
        <x-card title="Products" subtitle="Select existing products or add new ones">
            <div id="itemsContainer" class="space-y-4">
                {{-- First item row (template) --}}
                <div class="item-row rounded-xl border border-gray-200 bg-gray-50/50 p-4" data-index="0">
                    {{-- Mode Toggle --}}
                    <div class="flex items-center gap-2 mb-4 flex-wrap">
                        <button type="button" class="mode-toggle active rounded-lg px-3 py-1.5 text-xs font-semibold transition-all bg-brand-600 text-white shadow-sm" data-mode="existing">
                            <i class="bi bi-search mr-1"></i> Existing Product
                        </button>
                        <button type="button" class="mode-toggle rounded-lg px-3 py-1.5 text-xs font-semibold transition-all bg-white text-gray-500 border border-gray-200 hover:border-brand-300 hover:text-brand-600" data-mode="new">
                            <i class="bi bi-plus-circle mr-1"></i> New Product
                        </button>
                        <div class="flex-1"></div>
                        <button type="button" class="remove-item rounded-lg p-1.5 text-gray-400 hover:text-rose-600 hover:bg-rose-50 transition-all" title="Remove">
                            <i class="bi bi-trash text-sm"></i>
                        </button>
                    </div>

                    {{-- Existing Product Fields --}}
                    <div class="existing-fields">
                        <div class="mb-3">
                            <label class="block text-[10px] font-bold uppercase tracking-wider text-gray-400 mb-1">Product *</label>
                            <div class="relative">
                                <input type="text" class="product-search w-full rounded-lg border border-gray-200 bg-white px-3 py-2.5 text-sm focus:border-brand-500 focus:ring-1 focus:ring-brand-500 focus:outline-none transition-all" placeholder="Search by name or SKU..." autocomplete="off">
                                <input type="hidden" name="items[0][product_id]" class="product-id">
                                <div class="product-dropdown hidden absolute z-50 mt-1 w-full rounded-xl border border-gray-200 bg-white shadow-lg max-h-48 overflow-y-auto"></div>
                                <div class="selected-product-indicator hidden absolute right-2 top-1/2 -translate-y-1/2 flex items-center gap-1">
                                    <span class="text-brand-600 text-xs">✓</span>
                                    <button type="button" class="clear-product text-gray-400 hover:text-gray-600 text-xs p-0.5">✕</button>
                                </div>
                            </div>
                        </div>
                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <label class="block text-[10px] font-bold uppercase tracking-wider text-gray-400 mb-1">Qty *</label>
                                <input type="number" name="items[0][quantity]" class="item-qty w-full rounded-lg border border-gray-200 bg-white px-3 py-2.5 text-sm focus:border-brand-500 focus:ring-1 focus:ring-brand-500 focus:outline-none transition-all" placeholder="Qty" min="1" required>
                            </div>
                            <div>
                                <label class="block text-[10px] font-bold uppercase tracking-wider text-gray-400 mb-1">Cost (₹) *</label>
                                <input type="number" name="items[0][unit_cost]" class="item-cost w-full rounded-lg border border-gray-200 bg-white px-3 py-2.5 text-sm focus:border-brand-500 focus:ring-1 focus:ring-brand-500 focus:outline-none transition-all" placeholder="₹0.00" step="0.01" min="0" required>
                            </div>
                        </div>
                    </div>

                    {{-- Row Total --}}
                    <div class="mt-3 flex justify-end">
                        <span class="item-total text-sm font-semibold text-gray-900">₹0</span>
                    </div>

                    {{-- New Product Fields --}}
                    <div class="new-fields hidden">
                        <div class="space-y-3">
                            <div>
                                <label class="block text-[10px] font-bold uppercase tracking-wider text-gray-400 mb-1">Product Name *</label>
                                <input type="text" name="items[0][product_name]" class="w-full rounded-lg border border-gray-200 bg-white px-3 py-2.5 text-sm focus:border-brand-500 focus:ring-1 focus:ring-brand-500 focus:outline-none transition-all" placeholder="e.g. Wireless Mouse">
                            </div>
                            <div>
                                <label class="block text-[10px] font-bold uppercase tracking-wider text-gray-400 mb-1">SKU *</label>
                                <input type="text" name="items[0][product_sku]" class="w-full rounded-lg border border-gray-200 bg-white px-3 py-2.5 text-sm focus:border-brand-500 focus:ring-1 focus:ring-brand-500 focus:outline-none transition-all" placeholder="ELEC-WM-001">
                            </div>
                            <div class="grid grid-cols-2 gap-3">
                                <div>
                                    <label class="block text-[10px] font-bold uppercase tracking-wider text-gray-400 mb-1">Qty *</label>
                                    <input type="number" name="items[0][new_quantity]" class="item-qty w-full rounded-lg border border-gray-200 bg-white px-3 py-2.5 text-sm focus:border-brand-500 focus:ring-1 focus:ring-brand-500 focus:outline-none transition-all" placeholder="Qty" min="1">
                                </div>
                                <div>
                                    <label class="block text-[10px] font-bold uppercase tracking-wider text-gray-400 mb-1">Cost (₹) *</label>
                                    <input type="number" name="items[0][new_unit_cost]" class="item-cost w-full rounded-lg border border-gray-200 bg-white px-3 py-2.5 text-sm focus:border-brand-500 focus:ring-1 focus:ring-brand-500 focus:outline-none transition-all" placeholder="₹0.00" step="0.01" min="0">
                                </div>
                            </div>
                            <div class="grid grid-cols-2 gap-3">
                                <div>
                                    <label class="block text-[10px] font-bold uppercase tracking-wider text-gray-400 mb-1">Image (optional)</label>
                                    <input type="file" name="items[0][product_image]" accept="image/*" class="w-full rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm text-gray-600 file:mr-3 file:rounded-lg file:border-0 file:bg-brand-50 file:px-3 file:py-1 file:text-sm file:font-medium file:text-brand-700 hover:file:bg-brand-100 transition-all">
                                </div>
                                <div>
                                    <label class="block text-[10px] font-bold uppercase tracking-wider text-gray-400 mb-1">Sell Price (₹)</label>
                                    <input type="number" name="items[0][selling_price]" class="w-full rounded-lg border border-gray-200 bg-white px-3 py-2.5 text-sm focus:border-brand-500 focus:ring-1 focus:ring-brand-500 focus:outline-none transition-all" placeholder="₹0.00" step="0.01" min="0">
                                </div>
                            </div>
                            <div>
                                <label class="block text-[10px] font-bold uppercase tracking-wider text-gray-400 mb-1">Low Stock Alert</label>
                                <input type="number" name="items[0][reorder_threshold]" class="w-full rounded-lg border border-gray-200 bg-white px-3 py-2.5 text-sm focus:border-brand-500 focus:ring-1 focus:ring-brand-500 focus:outline-none transition-all" placeholder="5" min="0" value="5">
                            </div>
                        </div>
                    </div>
                </div>
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
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 rounded-t-xl lg:rounded-xl border border-gray-200 bg-white px-5 py-4 shadow-sm lg:shadow-sm shadow-[0_-2px_10px_rgba(0,0,0,0.06)]">
                <div>
                    <p class="text-xs font-bold uppercase tracking-wider text-gray-400">Total Cost</p>
                    <p class="text-2xl font-bold text-gray-900" id="totalCost">₹0</p>
                </div>
                <div class="flex items-center gap-3 sm:shrink-0">
                    <a href="{{ route('batch-orders.index') }}" class="rounded-xl border border-gray-300 bg-white px-5 py-2.5 text-sm font-medium text-gray-700 hover:bg-gray-50 transition-all">Cancel</a>
                    <button type="submit" class="touch-target rounded-xl bg-brand-600 px-6 py-2.5 text-sm font-semibold text-white shadow-lg shadow-brand-200 hover:bg-brand-700 transition-all">
                        <i class="bi bi-check-lg mr-1"></i> Create Batch Order
                    </button>
                </div>
            </div>
        </div>
    </form>
</div>
@endsection

@section('scripts')
@php
    $productsJson = $products->map(fn($p) => ['id' => $p->id, 'name' => $p->name, 'sku' => $p->sku, 'cost_price' => $p->cost_price])->values();
@endphp
<script>
// Products data from server for search
const products = {!! $productsJson !!};

let itemIndex = 1;

// Add item
document.getElementById('addItem').addEventListener('click', function() {
    const container = document.getElementById('itemsContainer');
    const firstRow = container.querySelector('.item-row');
    const clone = firstRow.cloneNode(true);

    // Update all names and clear values
    clone.querySelectorAll('input, select').forEach(el => {
        if (el.name) el.name = el.name.replace(/\[\d+\]/, `[${itemIndex}]`);
        if (el.type !== 'hidden' && el.type !== 'file') el.value = '';
    });
    clone.querySelectorAll('.product-id').forEach(el => el.value = '');
    clone.querySelectorAll('.product-search').forEach(el => el.value = '');
    clone.querySelector('.item-total').textContent = '₹0';

    // Reset to existing mode
    clone.querySelectorAll('.mode-toggle').forEach(btn => {
        btn.classList.remove('active', 'bg-brand-600', 'text-white', 'shadow-sm');
        btn.classList.add('bg-white', 'text-gray-500', 'border', 'border-gray-200');
    });
    const existingBtn = clone.querySelector('[data-mode="existing"]');
    existingBtn.classList.add('active', 'bg-brand-600', 'text-white', 'shadow-sm');
    existingBtn.classList.remove('bg-white', 'text-gray-500', 'border', 'border-gray-200');
    clone.querySelector('.existing-fields').classList.remove('hidden');
    clone.querySelector('.new-fields').classList.add('hidden');

    container.appendChild(clone);
    itemIndex++;
    bindEvents(clone);
});

function bindEvents(row) {
    const rows = row ? [row] : document.querySelectorAll('.item-row');

    rows.forEach(r => {
        // Mode toggle
        r.querySelectorAll('.mode-toggle').forEach(btn => {
            btn.onclick = function() {
                const mode = this.dataset.mode;
                r.querySelectorAll('.mode-toggle').forEach(b => {
                    b.classList.remove('active', 'bg-brand-600', 'text-white', 'shadow-sm');
                    b.classList.add('bg-white', 'text-gray-500', 'border', 'border-gray-200');
                });
                this.classList.add('active', 'bg-brand-600', 'text-white', 'shadow-sm');
                this.classList.remove('bg-white', 'text-gray-500', 'border', 'border-gray-200');

                if (mode === 'existing') {
                    r.querySelector('.existing-fields').classList.remove('hidden');
                    r.querySelector('.new-fields').classList.add('hidden');
                } else {
                    r.querySelector('.existing-fields').classList.add('hidden');
                    r.querySelector('.new-fields').classList.remove('hidden');
                }
            };
        });

        // Remove item
        const removeBtn = r.querySelector('.remove-item');
        if (removeBtn) {
            removeBtn.onclick = function() {
                if (document.querySelectorAll('.item-row').length > 1) {
                    r.remove();
                    reindexItems();
                    calculateTotal();
                }
            };
        }

        // Product search
        const searchInput = r.querySelector('.product-search');
        const dropdown = r.querySelector('.product-dropdown');
        const hiddenId = r.querySelector('.product-id');

        if (searchInput) {
            searchInput.oninput = function() {
                const term = this.value.toLowerCase().trim();
                if (term.length < 1) {
                    dropdown.classList.add('hidden');
                    return;
                }
                const matches = products.filter(p =>
                    p.name.toLowerCase().includes(term) || p.sku.toLowerCase().includes(term)
                ).slice(0, 8);                if (matches.length === 0) {
                    dropdown.innerHTML = '<div class="px-3 py-2 text-sm text-gray-500">No products found. Try "New Product" instead.</div>';
                    dropdown.classList.remove('hidden');
                    return;
                }

                const selectedId = hiddenId.value;
                dropdown.innerHTML = matches.map(p => `
                    <div class="product-option px-3 py-2 cursor-pointer hover:bg-brand-50 transition-colors border-b border-gray-50 last:border-0 ${selectedId == p.id ? 'bg-brand-50 border-l-2 border-l-brand-500' : ''}" data-id="${p.id}" data-cost="${p.cost_price}">
                        <div class="flex items-center justify-between">
                            <span class="text-sm font-medium text-gray-900">${p.name}</span>
                            ${selectedId == p.id ? '<span class="text-brand-600 text-xs">✓</span>' : ''}
                        </div>
                        <div class="text-xs text-gray-500">${p.sku} · Last cost: ₹${parseFloat(p.cost_price).toLocaleString()}</div>
                    </div>
                `).join('');
                dropdown.classList.remove('hidden');

                // Bind option clicks
                dropdown.querySelectorAll('.product-option').forEach(opt => {
                    opt.onclick = function() {
                        hiddenId.value = this.dataset.id;
                        searchInput.value = this.querySelector('.text-sm').textContent;
                        const costField = r.querySelector('input[name*="unit_cost"]:not([name*="new_"])');
                        if (costField && this.dataset.cost) costField.value = this.dataset.cost;
                        
                        // Show selected indicator
                        const indicator = r.querySelector('.selected-product-indicator');
                        if (indicator) {
                            indicator.classList.remove('hidden');
                            searchInput.classList.add('border-brand-500', 'bg-brand-50');
                            searchInput.classList.remove('border-gray-200', 'bg-white');
                        }
                        
                        dropdown.classList.add('hidden');
                        calculateRowTotal(r);
                        calculateTotal();
                    };
                });
            };

            searchInput.onfocus = function() {
                if (this.value.trim().length > 0) this.oninput();
            };
        }

        // Close dropdown on outside click
        document.addEventListener('click', function(e) {
            if (!e.target.closest('.product-search') && !e.target.closest('.product-dropdown') && !e.target.closest('.clear-product')) {
                document.querySelectorAll('.product-dropdown').forEach(d => d.classList.add('hidden'));
            }
            
            // Handle clear button click
            if (e.target.closest('.clear-product')) {
                e.preventDefault();
                e.stopPropagation();
                const row = e.target.closest('.item-row');
                if (row) {
                    const searchInput = row.querySelector('.product-search');
                    const hiddenId = row.querySelector('.product-id');
                    const indicator = row.querySelector('.selected-product-indicator');
                    if (searchInput) searchInput.value = '';
                    if (hiddenId) hiddenId.value = '';
                    if (indicator) indicator.classList.add('hidden');
                    if (searchInput) {
                        searchInput.classList.remove('border-brand-500', 'bg-brand-50');
                        searchInput.classList.add('border-gray-200', 'bg-white');
                    }
                }
            }
        });

        // Qty/cost change → update row total
        r.querySelectorAll('.item-qty, .item-cost').forEach(inp => {
            inp.oninput = function() { calculateRowTotal(r); calculateTotal(); };
        });
    });
}

function calculateRowTotal(row) {
    const qty = parseFloat(row.querySelector('.item-qty')?.value) || 0;
    const cost = parseFloat(row.querySelector('.item-cost')?.value) || 0;
    row.querySelector('.item-total').textContent = '₹' + (qty * cost).toLocaleString(undefined, {minimumFractionDigits: 0, maximumFractionDigits: 2});
}

function calculateTotal() {
    let total = 0;
    document.querySelectorAll('.item-total').forEach(el => {
        total += parseFloat(el.textContent.replace('₹', '').replace(/,/g, '')) || 0;
    });
    document.getElementById('totalCost').textContent = '₹' + total.toLocaleString(undefined, {minimumFractionDigits: 0, maximumFractionDigits: 2});
}

function reindexItems() {
    document.querySelectorAll('.item-row').forEach((row, i) => {
        row.querySelectorAll('input, select').forEach(el => {
            if (el.name) el.name = el.name.replace(/\[\d+\]/, `[${i}]`);
        });
    });
}

// Form validation before submit
document.getElementById('batchForm').addEventListener('submit', function(e) {
    const rows = document.querySelectorAll('.item-row');
    let valid = true;

    rows.forEach((row, i) => {
        const isExisting = row.querySelector('.mode-toggle.active')?.dataset.mode === 'existing';

        if (isExisting) {
            const productId = row.querySelector('.product-id')?.value;
            const qty = row.querySelector('input[name*="quantity"]')?.value;
            const cost = row.querySelector('input[name*="unit_cost"]')?.value;
            if (!productId || !qty || !cost) { valid = false; row.classList.add('border-rose-300'); }
            else row.classList.remove('border-rose-300');
        } else {
            const name = row.querySelector('input[name*="product_name"]')?.value;
            const sku = row.querySelector('input[name*="product_sku"]')?.value;
            const qty = row.querySelector('input[name*="new_quantity"]')?.value;
            const cost = row.querySelector('input[name*="new_unit_cost"]')?.value;
            if (!name || !sku || !qty || !cost) { valid = false; row.classList.add('border-rose-300'); }
            else row.classList.remove('border-rose-300');
        }
    });

    if (!valid) {
        e.preventDefault();
        alert('Please fill in all required fields for each product row.');
    }
});

bindEvents();
</script>
@endsection
