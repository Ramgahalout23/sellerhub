@extends('layouts.app')
@section('title', 'New Order')
@section('subtitle', 'Create a sale with one or more products')

@section('content')
<div class="max-w-5xl space-y-6">

    <div class="flex flex-wrap items-center justify-between gap-2">
        <div>
            <h2 class="text-lg font-bold text-gray-900">Create Order</h2>
            <p class="text-sm text-gray-500">Add products sold to a customer</p>
        </div>
        <a href="{{ route('orders.index') }}" class="text-sm font-medium text-gray-500 hover:text-gray-700 transition-colors">
            <i class="bi bi-arrow-left mr-1"></i> Back to Orders
        </a>
    </div>

    <form action="{{ route('orders.store') }}" method="POST" id="orderForm" class="pb-36 lg:pb-0">
        @csrf

        {{-- Order Header --}}
        <x-card>
            <div class="grid grid-cols-1 gap-5 sm:grid-cols-3">
                <div>
                    <label class="block text-xs font-bold uppercase tracking-wider text-gray-500 mb-2">Order Number</label>
                    <input type="text" name="order_number" class="w-full rounded-xl border border-gray-200 bg-gray-50 px-4 py-2.5 text-sm focus:border-brand-500 focus:ring-2 focus:ring-brand-500/20 focus:outline-none transition-all" value="{{ old('order_number') }}" placeholder="Platform order ID">
                </div>
                <div>
                    <label class="block text-xs font-bold uppercase tracking-wider text-gray-500 mb-2">Platform *</label>
                    <select name="platform_id" id="platformSelect" class="w-full rounded-xl border border-gray-200 bg-gray-50 px-4 py-2.5 text-sm focus:border-brand-500 focus:ring-2 focus:ring-brand-500/20 focus:outline-none transition-all" required>
                        <option value="">Select platform...</option>
                        @foreach($platforms as $p)
                            <option value="{{ $p->id }}" data-fees="{{ $platformFeeNotes[$p->id] ?? '' }}" {{ old('platform_id') == $p->id ? 'selected' : '' }}>{{ $p->name }}</option>
                        @endforeach
                    </select>
                    <p class="platform-fee-note mt-1.5 text-[10px] font-medium text-gray-400 leading-tight"></p>
                </div>
                <div>
                    <label class="block text-xs font-bold uppercase tracking-wider text-gray-500 mb-2">Customer Name</label>
                    <input type="text" name="customer_name" class="w-full rounded-xl border border-gray-200 bg-gray-50 px-4 py-2.5 text-sm focus:border-brand-500 focus:ring-2 focus:ring-brand-500/20 focus:outline-none transition-all" value="{{ old('customer_name') }}" placeholder="Customer name">
                </div>
                <div>
                    <label class="block text-xs font-bold uppercase tracking-wider text-gray-500 mb-2">Payment</label>
                    <select name="payment_mode" class="w-full rounded-xl border border-gray-200 bg-gray-50 px-4 py-2.5 text-sm focus:border-brand-500 focus:ring-2 focus:ring-brand-500/20 focus:outline-none transition-all">
                        <option value="prepaid" {{ old('payment_mode', 'prepaid') === 'prepaid' ? 'selected' : '' }}>Prepaid (paid online)</option>
                        <option value="cod" {{ old('payment_mode') === 'cod' ? 'selected' : '' }}>Cash on Delivery</option>
                    </select>
                    <p class="mt-1.5 text-[10px] text-gray-400 leading-tight">COD orders carry most return/RTO risk</p>
                </div>
            </div>
            <div class="mt-4">
                <label class="block text-xs font-bold uppercase tracking-wider text-gray-500 mb-2">Notes</label>
                <input type="text" name="notes" class="w-full rounded-xl border border-gray-200 bg-gray-50 px-4 py-2.5 text-sm focus:border-brand-500 focus:ring-2 focus:ring-brand-500/20 focus:outline-none transition-all" value="{{ old('notes') }}" placeholder="e.g. Express delivery, gift wrap">
            </div>
        </x-card>

        {{-- Line Items --}}
        <x-card title="Products" subtitle="Add one or more products to this order">
            <div id="itemsContainer" class="space-y-4">
                {{-- First item row (template) --}}
                <div class="item-row rounded-xl border border-gray-200 bg-gray-50/50 p-4" data-index="0">
                    <div class="grid grid-cols-1 gap-3 sm:grid-cols-12">
                        <div class="sm:col-span-5">
                            <label class="block text-[10px] font-bold uppercase tracking-wider text-gray-400 mb-1">Product *</label>
                            <select name="items[0][product_id]" class="w-full rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm focus:border-brand-500 focus:ring-1 focus:ring-brand-500 focus:outline-none transition-all product-select" required>
                                <option value="">Select product...</option>
                                @foreach($products as $p)
                                    <option value="{{ $p->id }}" data-cost="{{ $p->cost_price }}" data-price="{{ $p->selling_price }}" data-stock="{{ $p->stock_quantity }}">{{ $p->name }} ({{ $p->sku }}) — Stock: {{ $p->stock_quantity }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="sm:col-span-2">
                            <label class="block text-[10px] font-bold uppercase tracking-wider text-gray-400 mb-1">Quantity *</label>
                            <input type="number" name="items[0][quantity]" class="item-qty w-full rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm focus:border-brand-500 focus:ring-1 focus:ring-brand-500 focus:outline-none transition-all" placeholder="Qty" min="1" value="1" required>
                        </div>
                        <div class="sm:col-span-2">
                            <label class="block text-[10px] font-bold uppercase tracking-wider text-gray-400 mb-1">Price/Unit (₹) *</label>
                            <input type="number" name="items[0][selling_price]" class="item-price w-full rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm focus:border-brand-500 focus:ring-1 focus:ring-brand-500 focus:outline-none transition-all" placeholder="₹0" step="0.01" min="0" required>
                        </div>
                        <div class="sm:col-span-2 flex items-end">
                            <div class="text-sm font-bold text-gray-900 item-total">₹0</div>
                        </div>
                        <div class="sm:col-span-1 flex items-end justify-end">
                            <button type="button" class="remove-item touch-target flex items-center justify-center rounded-lg text-gray-400 hover:text-rose-600 hover:bg-rose-50 transition-all" title="Remove" aria-label="Remove this product"><i class="bi bi-trash text-base"></i></button>
                        </div>
                    </div>

                    {{-- Stock Source Selector --}}
                    <div class="stock-source-preview mt-3 hidden">
                        <div class="rounded-lg bg-blue-50 border border-blue-100 p-3">
                            <div class="flex items-center justify-between mb-2">
                                <div class="flex items-center gap-1.5">
                                    <i class="bi bi-truck text-blue-600 text-xs"></i>
                                    <span class="text-[10px] font-bold uppercase tracking-wider text-blue-700">Select Supplier Batch *</span>
                                </div>
                                <span class="stock-avail-badge text-[10px] font-semibold text-blue-600"></span>
                            </div>
                            <div class="stock-source-list space-y-1.5"></div>
                            <input type="hidden" name="items[0][stock_batch_id]" class="stock-batch-id" value="">
                        </div>
                    </div>

                    {{-- Per-item charges --}}
                    <div class="mt-3 border-t border-gray-100 pt-3">
                        <div class="flex items-center gap-2 mb-2">
                            <span class="text-[10px] font-bold uppercase tracking-wider text-gray-400">Charges</span>
                            <button type="button" class="add-charge text-[10px] font-medium text-brand-600 hover:text-brand-700">+ Add</button>
                        </div>
                        <div class="charges-container space-y-2">
                            <div class="charge-row grid grid-cols-12 gap-2 items-center">
                                <div class="col-span-5"><input type="text" name="items[0][charges][0][charge_name]" class="w-full rounded-lg border border-gray-200 bg-white px-2.5 py-1.5 text-xs focus:border-brand-500 focus:ring-1 focus:ring-brand-500 focus:outline-none" placeholder="e.g. shipping, GST"></div>
                                <div class="col-span-5"><input type="number" name="items[0][charges][0][amount]" class="w-full rounded-lg border border-gray-200 bg-white px-2.5 py-1.5 text-xs focus:border-brand-500 focus:ring-1 focus:ring-brand-500 focus:outline-none" placeholder="₹0" step="0.01" min="0"></div>
                                <div class="col-span-2 text-right"><button type="button" class="remove-charge touch-target flex items-center justify-center rounded-lg text-gray-400 hover:text-rose-600" aria-label="Remove this charge"><i class="bi bi-trash text-base"></i></button></div>
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

        {{-- Grand Total + Submit (compact fixed bar above bottom nav) --}}
        <div class="fixed bottom-16 left-0 right-0 z-40 lg:static lg:bottom-auto border-t border-gray-100">
            <div class="flex items-center justify-between gap-3 rounded-t-xl lg:rounded-xl border border-gray-200 bg-white px-4 py-2.5 lg:px-5 lg:py-4 shadow-sm lg:shadow-sm shadow-[0_-2px_10px_rgba(0,0,0,0.06)]">
                <div class="min-w-0">
                    <p class="text-[10px] font-bold uppercase tracking-wider text-gray-400 leading-tight">Total</p>
                    <p class="text-lg lg:text-2xl font-bold text-gray-900 leading-tight truncate" id="grandTotal">₹0</p>
                    <p class="text-[10px] lg:text-xs text-gray-400 leading-tight truncate"><span id="itemCount">1</span> product(s) · Status: Pending</p>
                    <p class="text-[10px] lg:text-xs font-medium text-amber-600 leading-tight truncate hidden" id="chargeSummary"></p>
                </div>
                <div class="flex items-center gap-2 shrink-0">
                    <a href="{{ route('orders.index') }}" class="touch-target inline-flex items-center justify-center rounded-xl border border-gray-300 bg-white px-3 lg:px-5 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50 transition-all">Cancel</a>
                    <button type="submit" class="touch-target inline-flex items-center justify-center rounded-xl bg-brand-600 px-3.5 lg:px-6 py-2 text-sm font-semibold text-white shadow-lg shadow-brand-200 hover:bg-brand-700 transition-all whitespace-nowrap">
                        <i class="bi bi-check-lg mr-1"></i> Create<span class="hidden sm:inline"> Order</span>
                    </button>
                </div>
            </div>
        </div>
    </form>
</div>
@endsection

@section('scripts')
<script>
const products = @json($products);
let itemIndex = 1;

// Add item
document.getElementById('addItem').addEventListener('click', function() {
    const container = document.getElementById('itemsContainer');
    const template = container.querySelector('.item-row');
    const clone = template.cloneNode(true);

    // Update indices
    clone.dataset.index = itemIndex;
    clone.querySelectorAll('select, input').forEach(el => {
        if (el.name) el.name = el.name.replace(/\[\d+\]/, `[${itemIndex}]`);
        if (el.tagName === 'SELECT') el.selectedIndex = 0;
        else el.value = el.type === 'number' ? (el.classList.contains('item-qty') ? '1' : '') : '';
    });
    clone.querySelector('.item-total').textContent = '₹0';
    clone.querySelector('.stock-source-preview')?.classList.add('hidden');
    clone.querySelector('.stock-source-list').innerHTML = '';
    var batchInput = clone.querySelector('.stock-batch-id');
    if (batchInput) { batchInput.name = 'items[' + itemIndex + '][stock_batch_id]'; batchInput.value = ''; }
    clone.querySelector('.charges-container').innerHTML = chargeRowHtml(itemIndex, 0);
    // The clone inherits the template row's data-* state; a new row starts clean,
    // and auto-fill applies to it until the user types a charge by hand.
    delete clone.dataset.chargesTotal;
    delete clone.dataset.chargesAuto;

    container.appendChild(clone);
    itemIndex++;
    bindEvents(clone);
    calculateTotal();
});

// Bind events on a row
function bindEvents(row) {
    row.querySelector('.remove-item')?.addEventListener('click', function() {
        if (document.querySelectorAll('.item-row').length > 1) {
            this.closest('.item-row').remove();
            calculateTotal();
        }
    });

    row.querySelector('.product-select')?.addEventListener('change', function() {
        const opt = this.options[this.selectedIndex];
        if (opt.value) {
            const priceInput = row.querySelector('.item-price');
            if (priceInput) priceInput.value = opt.dataset.price || '';
            calculateRowTotal(row);
            calculateTotal();
            loadStockSource(row, opt.value);
            fillChargesSoon();
        } else {
            row.querySelector('.stock-source-preview')?.classList.add('hidden');
        }
    });

    row.querySelectorAll('.item-qty, .item-price').forEach(inp => {
        inp.addEventListener('input', function() { calculateRowTotal(row); calculateTotal(); fillChargesSoon(); });
    });

    row.querySelector('.add-charge')?.addEventListener('click', function() {
        const container = row.querySelector('.charges-container');
        const chargeIdx = container.querySelectorAll('.charge-row').length;
        container.insertAdjacentHTML('beforeend', chargeRowHtml(row.dataset.index, chargeIdx));
        // Adding a charge by hand means the user owns the numbers on this row.
        row.dataset.chargesAuto = '0';
        bindChargeRemovers(row);
        updateChargeSummary();
    });

    bindChargeRemovers(row);
}

function calculateRowTotal(row) {
    const qty = parseFloat(row.querySelector('.item-qty')?.value) || 0;
    const price = parseFloat(row.querySelector('.item-price')?.value) || 0;
    const total = qty * price;
    row.querySelector('.item-total').textContent = '₹' + total.toLocaleString(undefined, {minimumFractionDigits: 0, maximumFractionDigits: 2});
}

function calculateTotal() {
    let total = 0;
    let count = 0;
    document.querySelectorAll('.item-row').forEach(row => {
        const qty = parseFloat(row.querySelector('.item-qty')?.value) || 0;
        const price = parseFloat(row.querySelector('.item-price')?.value) || 0;
        total += qty * price;
        if (row.querySelector('.product-select')?.value) count++;
    });
    document.getElementById('grandTotal').textContent = '₹' + total.toLocaleString(undefined, {minimumFractionDigits: 0, maximumFractionDigits: 2});
    document.getElementById('itemCount').textContent = count;
    updateChargeSummary();
}

// Load stock source (selectable) for selected product
function loadStockSource(row, productId) {
    const preview = row.querySelector('.stock-source-preview');
    const list = row.querySelector('.stock-source-list');
    const badge = row.querySelector('.stock-avail-badge');
    const hiddenInput = row.querySelector('.stock-batch-id');
    if (!preview || !list) return;

    fetch('/api/products/' + productId + '/stock-batches')
        .then(function(r) { return r.json(); })
        .then(function(batches) {
            if (!batches || batches.length === 0) {
                preview.classList.add('hidden');
                return;
            }
            var totalAvail = batches.reduce(function(s, b) { return s + b.remaining; }, 0);
            if (badge) badge.textContent = totalAvail + ' units available';

            var html = '';
            batches.forEach(function(b, i) {
                var checked = i === 0 ? 'checked' : '';
                var borderColor = i === 0 ? 'border-blue-300 bg-blue-50' : 'border-gray-200 bg-white hover:border-blue-200';
                html += '<label class="batch-option flex items-center gap-3 rounded-lg border p-2.5 cursor-pointer transition-all ' + borderColor + '" data-batch-id="' + b.id + '" data-qty="' + b.remaining + '">';
                html += '<input type="radio" name="' + row.querySelector('.stock-batch-id').name + '" value="' + b.id + '" ' + checked + ' class="batch-radio w-4 h-4 text-blue-600 focus:ring-blue-500">';
                html += '<div class="flex-1 min-w-0">';
                html += '<div class="flex items-center gap-2">';
                html += '<span class="text-sm font-semibold text-gray-900 truncate">' + b.supplier + '</span>';
                html += '<span class="text-[10px] font-medium px-1.5 py-0.5 rounded bg-gray-100 text-gray-500">Batch #' + b.id + '</span>';
                if (i === 0) html += '<span class="text-[10px] font-bold px-1.5 py-0.5 rounded bg-blue-100 text-blue-700">FIFO</span>';
                html += '</div>';
                html += '<div class="flex items-center gap-3 mt-0.5">';
                html += '<span class="text-xs text-gray-500">' + b.remaining + ' units @ ₹' + b.unit_cost + '/unit</span>';
                html += '<span class="text-xs font-medium text-gray-600">Cost: ₹' + (b.remaining * b.unit_cost).toLocaleString('en-IN') + '</span>';
                html += '</div>';
                html += '</div>';
                html += '</label>';
            });
            list.innerHTML = html;
            if (hiddenInput) hiddenInput.value = batches[0].id;

            // Bind radio selection
            list.querySelectorAll('.batch-option').forEach(function(opt) {
                opt.addEventListener('click', function() {
                    list.querySelectorAll('.batch-option').forEach(function(o) {
                        o.classList.remove('border-blue-300', 'bg-blue-50');
                        o.classList.add('border-gray-200', 'bg-white');
                    });
                    this.classList.remove('border-gray-200', 'bg-white');
                    this.classList.add('border-blue-300', 'bg-blue-50');
                    this.querySelector('.batch-radio').checked = true;
                    if (hiddenInput) hiddenInput.value = this.dataset.batchId;
                });
            });

            preview.classList.remove('hidden');
        });
}

// ---- Per-item charges: markup + auto-fill from the platform's charge structure ----

function chargeRowHtml(idx, chargeIdx, name, amount) {
    name = name || '';
    amount = (amount === undefined || amount === null || amount === '') ? '' : amount;
    return `
        <div class="charge-row grid grid-cols-12 gap-2 items-center">
            <div class="col-span-5"><input type="text" name="items[${idx}][charges][${chargeIdx}][charge_name]" value="${name}" autocomplete="off" class="w-full rounded-lg border border-gray-200 bg-white px-2.5 py-1.5 text-xs focus:border-brand-500 focus:ring-1 focus:ring-brand-500 focus:outline-none" placeholder="e.g. shipping, GST"></div>
            <div class="col-span-5"><input type="number" name="items[${idx}][charges][${chargeIdx}][amount]" value="${amount}" class="w-full rounded-lg border border-gray-200 bg-white px-2.5 py-1.5 text-xs focus:border-brand-500 focus:ring-1 focus:ring-brand-500 focus:outline-none" placeholder="₹0" step="0.01" min="0"></div>
            <div class="col-span-2 text-right"><button type="button" class="remove-charge touch-target flex items-center justify-center rounded-lg text-gray-400 hover:text-rose-600" aria-label="Remove this charge"><i class="bi bi-trash text-base"></i></button></div>
        </div>`;
}

function bindChargeRemovers(row) {
    const container = row.querySelector('.charges-container');
    if (!container) return;

    container.querySelectorAll('.remove-charge').forEach(function(btn) {
        btn.onclick = function() {
            if (container.querySelectorAll('.charge-row').length > 1) {
                this.closest('.charge-row').remove();
            } else {
                // Never leave a row with no charge inputs at all.
                container.innerHTML = chargeRowHtml(row.dataset.index, 0);
                bindChargeRemovers(row);
            }
            updateChargeSummary();
        };
    });

    // The moment a value is typed, the user takes over this row: stop auto-filling it.
    container.querySelectorAll('input').forEach(function(inp) {
        inp.addEventListener('input', function() {
            row.dataset.chargesAuto = '0';
            updateChargeSummary();
        });
    });
}

function money(n) {
    return (Math.round(n * 100) / 100).toLocaleString('en-IN', {minimumFractionDigits: 0, maximumFractionDigits: 2});
}

// Responses are tiny and immutable for a given (platform, amount) pair, so one
// in-memory cache means typing a quantity costs zero extra requests.
const chargeCache = {};

function fetchChargePreview(platformId, amount) {
    const key = platformId + ':' + amount.toFixed(2);
    if (key in chargeCache) return Promise.resolve(chargeCache[key]);

    return fetch('{{ route('orders.charge-preview') }}?platform_id=' + encodeURIComponent(platformId) + '&amount=' + amount.toFixed(2), {
        headers: {'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest'}
    }).then(function(r) {
        return r.ok ? r.json() : null;
    }).then(function(json) {
        chargeCache[key] = json;
        return json;
    }).catch(function() { return null; });
}

function renderRowCharges(row, preview) {
    const container = row.querySelector('.charges-container');
    if (!container) return;

    const idx = row.dataset.index;
    const charges = (preview && preview.charges) ? preview.charges : [];

    container.innerHTML = charges.length
        ? charges.map(function(c, i) { return chargeRowHtml(idx, i, c.charge_name, c.amount); }).join('')
        : chargeRowHtml(idx, 0);

    row.dataset.chargesTotal = preview ? preview.total : 0;
    bindChargeRemovers(row);
}

function fillPlatformCharges() {
    const platformSel = document.getElementById('platformSelect');
    if (!platformSel || !platformSel.value) return;

    document.querySelectorAll('.item-row').forEach(function(row) {
        if (row.dataset.chargesAuto === '0') return;

        const productSel = row.querySelector('.product-select');
        if (!productSel || !productSel.value) return;

        const qty = parseFloat(row.querySelector('.item-qty') ? row.querySelector('.item-qty').value : 0) || 0;
        const price = parseFloat(row.querySelector('.item-price') ? row.querySelector('.item-price').value : 0) || 0;
        const amount = qty * price;
        if (amount <= 0) return;

        fetchChargePreview(platformSel.value, amount).then(function(preview) {
            if (row.dataset.chargesAuto === '0') return; // user edited while we were fetching
            renderRowCharges(row, preview);
            updateChargeSummary();
        });
    });
}

function rowChargeTotal(row) {
    if (row.dataset.chargesAuto === '0') {
        let sum = 0;
        row.querySelectorAll('.charges-container input[type="number"]').forEach(function(inp) { sum += parseFloat(inp.value) || 0; });
        return sum;
    }
    return parseFloat(row.dataset.chargesTotal || 0) || 0;
}

function updateChargeSummary() {
    const el = document.getElementById('chargeSummary');
    if (!el) return;

    let fees = 0, revenue = 0, hasFees = false;
    document.querySelectorAll('.item-row').forEach(function(row) {
        const rowFees = rowChargeTotal(row);
        if (rowFees > 0) { fees += rowFees; hasFees = true; }
        const qty = parseFloat(row.querySelector('.item-qty') ? row.querySelector('.item-qty').value : 0) || 0;
        const price = parseFloat(row.querySelector('.item-price') ? row.querySelector('.item-price').value : 0) || 0;
        revenue += qty * price;
    });

    if (!hasFees) {
        el.classList.add('hidden');
        el.textContent = '';
        return;
    }

    el.classList.remove('hidden');
    el.textContent = 'Platform fees ≈ ₹' + money(fees) + ' · Net ≈ ₹' + money(revenue - fees);
}

function updatePlatformFeeNote() {
    const sel = document.getElementById('platformSelect');
    const note = document.querySelector('.platform-fee-note');
    if (!sel || !note) return;

    const opt = sel.options[sel.selectedIndex];
    const fees = opt && opt.dataset ? (opt.dataset.fees || '') : '';
    note.textContent = fees ? 'Charges: ' + fees : '';
}

function debounce(fn, ms) {
    let t;
    return function() { clearTimeout(t); t = setTimeout(fn, ms); };
}

const fillChargesSoon = debounce(fillPlatformCharges, 350);

// Init first row
bindEvents(document.querySelector('.item-row'));

document.getElementById('platformSelect')?.addEventListener('change', function() {
    updatePlatformFeeNote();
    fillPlatformCharges();
});
updatePlatformFeeNote();
updateChargeSummary();
</script>
@endsection
