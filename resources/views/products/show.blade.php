@extends('layouts.app')
@section('title', $product->name)
@section('subtitle', 'SKU: ' . $product->sku)

@section('actions')
<a href="{{ route('products.edit', $product) }}" class="touch-target inline-flex items-center gap-1.5 rounded-xl border border-gray-300 bg-white px-3 py-2 text-sm font-medium text-gray-700 shadow-sm hover:bg-gray-50 transition-all">
    <i class="bi bi-pencil text-xs"></i> <span class="hidden sm:inline">Edit</span>
</a>
@endsection

@section('content')
<div class="grid grid-cols-1 gap-6 lg:grid-cols-4">
    <!-- Left Column -->
    <div class="lg:col-span-1 space-y-4">
        <!-- Product Info -->
        <x-card>
            <h5 class="text-base font-bold text-gray-900 mb-3">{{ $product->name }}</h5>
            <p class="text-xs text-gray-500 mb-3">{{ $product->description }}</p>
            <div class="space-y-3">
                <div>
                    <p class="text-xs text-gray-400">Cost Price</p>
                    <p class="text-base font-bold text-gray-900">₹{{ number_format($product->cost_price) }}</p>
                </div>
                <div>
                    <p class="text-xs text-gray-400">Selling Price</p>
                    <p class="text-base font-bold text-gray-900">₹{{ number_format($product->selling_price) }}</p>
                </div>
                <div>
                    <p class="text-xs text-gray-400">Stock</p>
                    @if($product->stock_quantity <= 0)
                        <x-badge variant="danger">0 units</x-badge>
                    @elseif($product->stock_quantity <= $product->reorder_threshold)
                        <x-badge variant="warning">{{ $product->stock_quantity }} units</x-badge>
                    @else
                        <x-badge variant="success">{{ $product->stock_quantity }} units</x-badge>
                    @endif
                </div>
                <div>
                    <p class="text-xs text-gray-400">Reorder At</p>
                    <p class="text-sm text-gray-900">{{ $product->reorder_threshold }} units</p>
                </div>
                <div class="border-t border-gray-100 pt-3">
                    <p class="text-xs text-gray-400">Margin</p>
                    <p class="text-base font-bold {{ $product->selling_price > $product->cost_price ? 'text-emerald-600' : 'text-rose-600' }}">
                        ₹{{ number_format($product->selling_price - $product->cost_price) }} ({{ $product->cost_price > 0 ? round(($product->selling_price - $product->cost_price) / $product->cost_price * 100) : 0 }}%)
                    </p>
                </div>
            </div>
        </x-card>

        <!-- P&L Summary -->
        <x-card title="Profit / Loss">
            <div class="space-y-3">
                <div class="grid grid-cols-2 gap-2">
                    <div><p class="text-xs text-gray-400">Purchased</p><p class="text-sm font-bold text-gray-900">{{ $total_purchased }} units</p></div>
                    <div><p class="text-xs text-gray-400">Sold</p><p class="text-sm font-bold text-emerald-600">{{ $total_sold }} units</p></div>
                </div>
                <div class="grid grid-cols-2 gap-2">
                    <div><p class="text-xs text-gray-400">Returned</p><p class="text-sm font-bold text-amber-600">{{ $total_returned }} units</p></div>
                    @if($product->total_missing > 0)
                    <div><p class="text-xs text-gray-400">Missing</p><p class="text-sm font-bold text-rose-600">{{ $product->total_missing }} units</p></div>
                    @endif
                </div>
                <div class="border-t border-gray-100 pt-3 space-y-3">
                    <div><p class="text-xs text-gray-400">Total Invested</p><p class="text-sm font-bold text-gray-900">₹{{ number_format($total_invested) }}</p></div>
                    <div><p class="text-xs text-gray-400">Total Received</p><p class="text-sm font-bold text-gray-900">₹{{ number_format($total_received) }}</p></div>
                    <div><p class="text-xs text-gray-400">Charges</p><p class="text-sm font-bold text-rose-600">-₹{{ number_format($total_charges) }}</p></div>
                    <div class="{{ $net_profit >= 0 ? 'bg-emerald-50' : 'bg-rose-50' }} -mx-3 px-3 py-2 rounded-xl">
                        <p class="text-xs text-gray-500">Net Profit/Loss</p>
                        <p class="text-lg font-bold {{ $net_profit >= 0 ? 'text-emerald-600' : 'text-rose-600' }}">₹{{ number_format($net_profit) }}</p>
                    </div>
                </div>
            </div>
        </x-card>

        <!-- Supplier Prices -->
        <x-card title="Supplier Prices" subtitle="What each supplier last charged — cheapest first">
            @if(empty($price_history['suppliers']))
                @forelse($suppliers as $s)
                    <a href="{{ route('suppliers.show', $s) }}" class="flex items-center justify-between py-3 {{ !$loop->last ? 'border-b border-gray-50' : '' }}">
                        <div>
                            <p class="text-sm font-medium text-gray-900">{{ $s->name }}</p>
                            <p class="text-[11px] text-gray-400">Last purchase price</p>
                        </div>
                        <p class="text-base font-bold text-gray-900">₹{{ number_format($s->pivot->last_known_price ?? 0) }}</p>
                    </a>
                @empty
                    <p class="text-xs text-gray-400 text-center py-2">No purchases yet — record a batch order to compare prices.</p>
                @endforelse
            @else
                @if(count($price_history['suppliers']) > 1 && $price_history['spread'] > 0)
                    <div class="mb-3 rounded-xl bg-emerald-50 border border-emerald-100 p-3 text-xs text-emerald-800">
                        Cheapest now: <span class="font-bold">{{ $price_history['cheapest']['supplier_name'] }}</span>
                        at <span class="font-bold">₹{{ number_format($price_history['cheapest_cost']) }}</span>
                        — ₹{{ number_format($price_history['spread']) }} below the most expensive.
                    </div>
                @endif

                <div class="space-y-1">
                    @foreach($price_history['suppliers'] as $entry)
                        @php
                            $isCheapest = $loop->first;
                            $change = $entry['change_percent'];
                            $rose = $change !== null && $change > 0;
                            $fell = $change !== null && $change < 0;
                        @endphp
                        <a href="{{ route('suppliers.show', $entry['supplier_id']) }}" class="flex items-center justify-between gap-2 py-2.5 {{ !$loop->last ? 'border-b border-gray-50' : '' }} hover:bg-gray-50/60 rounded-lg px-1 transition-colors">
                            <div class="min-w-0">
                                <p class="text-sm font-medium text-gray-900 truncate">
                                    {{ $entry['supplier_name'] }}
                                    @if($isCheapest)
                                        <x-badge variant="success" class="text-[10px]">Cheapest</x-badge>
                                    @endif
                                </p>
                                <p class="text-[11px] text-gray-400">
                                    {{ $entry['purchases'] }} purchase(s)
                                    · last {{ $entry['last_date'] ? $entry['last_date']->format('d M Y') : '—' }}
                                    @if($change !== null && $change != 0)
                                        · <span class="font-semibold {{ $rose ? 'text-rose-500' : 'text-emerald-600' }}">{{ $rose ? '▲' : '▼' }} {{ abs($change) }}%</span>
                                    @endif
                                </p>
                            </div>
                            <div class="text-right shrink-0">
                                <p class="text-base font-bold {{ $isCheapest ? 'text-emerald-600' : 'text-gray-900' }}">₹{{ number_format($entry['last_cost']) }}</p>
                                <p class="text-[10px] text-gray-400">best ₹{{ number_format($entry['min_cost']) }} · avg ₹{{ number_format($entry['avg_cost']) }}</p>
                            </div>
                        </a>
                    @endforeach
                </div>
            @endif
        </x-card>
    </div>

    <!-- Right Column -->
    <div class="lg:col-span-3 space-y-4">
        <!-- Purchase History -->
        <x-card title="Purchase History" subtitle="Batch order records">
            @if($product->batchOrderItems->isEmpty())
                <x-empty-state icon="cart-plus" title="No purchases yet" description="Create a batch order to record purchases." />
            @else
                {{-- Desktop table --}}
                <div class="hidden md:block overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="border-b border-gray-100">
                                <th class="pb-2.5 text-left text-[10px] font-bold uppercase tracking-wider text-gray-400">Date</th>
                                <th class="pb-2.5 text-left text-[10px] font-bold uppercase tracking-wider text-gray-400">Supplier</th>
                                <th class="pb-2.5 text-right text-[10px] font-bold uppercase tracking-wider text-gray-400">Qty</th>
                                <th class="pb-2.5 text-right text-[10px] font-bold uppercase tracking-wider text-gray-400">Unit Cost</th>
                                <th class="pb-2.5 text-right text-[10px] font-bold uppercase tracking-wider text-gray-400">Total</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-50">
                            @foreach($product->batchOrderItems->sortByDesc('batchOrder.order_date') as $item)
                            <tr class="hover:bg-gray-50/50 transition-colors">
                                <td class="py-2.5 text-gray-600">{{ $item->batchOrder->order_date->format('d M Y') }}</td>
                                <td class="py-2.5"><a href="{{ route('suppliers.show', $item->batchOrder->supplier) }}" class="font-medium text-brand-600 hover:text-brand-700">{{ $item->batchOrder->supplier->name }}</a></td>
                                <td class="py-2.5 text-right text-gray-600">{{ $item->quantity }}</td>
                                <td class="py-2.5 text-right text-gray-600">₹{{ number_format($item->unit_cost) }}</td>
                                <td class="py-2.5 text-right font-bold text-gray-900">₹{{ number_format($item->total_cost) }}</td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                {{-- Mobile cards --}}
                <div class="md:hidden space-y-3">
                    @foreach($product->batchOrderItems->sortByDesc('batchOrder.order_date') as $item)
                    <a href="{{ route('batch-orders.show', $item->batchOrder) }}" class="block p-3 rounded-xl border border-gray-100 hover:bg-gray-50">
                        <div class="flex items-start justify-between">
                            <div>
                                <p class="text-sm font-bold text-gray-900">{{ $item->batchOrder->supplier->name }}</p>
                                <p class="text-[11px] text-gray-400">{{ $item->batchOrder->order_date->format('d M Y') }}</p>
                            </div>
                            <div class="text-right">
                                <p class="text-sm font-bold text-gray-900">₹{{ number_format($item->total_cost) }}</p>
                                <p class="text-[11px] text-gray-400">{{ $item->quantity }} × ₹{{ number_format($item->unit_cost) }}</p>
                            </div>
                        </div>
                    </a>
                    @endforeach
                </div>
            @endif
        </x-card>

        <!-- Stock Batches (FIFO) -->
        <x-card title="Stock Batches" subtitle="FIFO tracking — oldest batches sold first">
            @if($stock_batches->isEmpty())
                <x-empty-state icon="boxes" title="No stock batches" description="Create a batch order to start tracking stock batches." />
            @else
                {{-- Mobile cards --}}
                <div class="md:hidden space-y-3">
                    @foreach($stock_batches as $batch)
                    @php $bv = match($batch->status) { 'available' => 'success', 'partial' => 'warning', 'depleted' => 'danger' }; @endphp
                    <div class="p-3 rounded-xl border border-gray-100">
                        <div class="flex items-center justify-between mb-1">
                            <span class="text-sm font-bold text-gray-900">Batch #{{ $batch->batchOrderItem->batch_order_id ?? '—' }}</span>
                            <x-badge :variant="$bv" size="xs">{{ ucfirst($batch->status) }}</x-badge>
                        </div>
                        <p class="text-[11px] text-gray-400">{{ $batch->supplier->name }} • {{ $batch->created_at->format('d M Y') }}</p>
                        <div class="grid grid-cols-3 gap-2 mt-2">
                            <div><p class="text-[10px] text-gray-400">Original</p><p class="text-sm font-bold text-gray-900">{{ $batch->original_quantity }}</p></div>
                            <div><p class="text-[10px] text-gray-400">Remaining</p><p class="text-sm font-bold {{ $batch->remaining_quantity > 0 ? 'text-emerald-600' : 'text-gray-400' }}">{{ $batch->remaining_quantity }}</p></div>
                            <div><p class="text-[10px] text-gray-400">Sold</p><p class="text-sm font-bold text-gray-900">{{ $batch->total_sold }}</p></div>
                        </div>
                        <p class="text-xs text-gray-500 mt-1">₹{{ number_format($batch->unit_cost) }}/unit</p>
                    </div>
                    @endforeach
                </div>
                {{-- Desktop table --}}
                <div class="hidden md:block overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="border-b border-gray-100">
                                <th class="pb-2.5 text-left text-[10px] font-bold uppercase tracking-wider text-gray-400">Batch #</th>
                                <th class="pb-2.5 text-left text-[10px] font-bold uppercase tracking-wider text-gray-400">Supplier</th>
                                <th class="pb-2.5 text-left text-[10px] font-bold uppercase tracking-wider text-gray-400">Date</th>
                                <th class="pb-2.5 text-right text-[10px] font-bold uppercase tracking-wider text-gray-400">Original</th>
                                <th class="pb-2.5 text-right text-[10px] font-bold uppercase tracking-wider text-gray-400">Remaining</th>
                                <th class="pb-2.5 text-right text-[10px] font-bold uppercase tracking-wider text-gray-400">Sold</th>
                                <th class="pb-2.5 text-right text-[10px] font-bold uppercase tracking-wider text-gray-400">Unit Cost</th>
                                <th class="pb-2.5 text-center text-[10px] font-bold uppercase tracking-wider text-gray-400">Status</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-50">
                            @foreach($stock_batches as $batch)
                            <tr class="hover:bg-gray-50/50 transition-colors">
                                <td class="py-2.5 text-gray-600">#{{ $batch->batchOrderItem->batch_order_id ?? '—' }}</td>
                                <td class="py-2.5">
                                    <a href="{{ route('suppliers.show', $batch->supplier) }}" class="font-medium text-brand-600 hover:text-brand-700">{{ $batch->supplier->name }}</a>
                                </td>
                                <td class="py-2.5 text-gray-600 text-xs">{{ $batch->created_at->format('d M Y') }}</td>
                                <td class="py-2.5 text-right text-gray-600">{{ $batch->original_quantity }}</td>
                                <td class="py-2.5 text-right">
                                    <span class="font-bold {{ $batch->remaining_quantity > 0 ? 'text-emerald-600' : 'text-gray-400' }}">{{ $batch->remaining_quantity }}</span>
                                </td>
                                <td class="py-2.5 text-right text-gray-600">{{ $batch->total_sold }}</td>
                                <td class="py-2.5 text-right text-gray-600">₹{{ number_format($batch->unit_cost) }}</td>
                                <td class="py-2.5 text-center">
                                    <x-badge :variant="$bv" size="xs">{{ ucfirst($batch->status) }}</x-badge>
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </x-card>

        <!-- Supplier Comparison -->
        @if(!empty($supplier_comparison))
        <x-card title="Supplier Comparison" subtitle="Quality & return rates by supplier for this product">
            {{-- Mobile cards --}}
            <div class="md:hidden space-y-3">
                @foreach($supplier_comparison as $sc)
                <a href="{{ route('suppliers.show', $sc['supplier']) }}" class="block p-3 rounded-xl border border-gray-100 hover:bg-gray-50">
                    <div class="flex items-center justify-between mb-2">
                        <p class="text-sm font-bold text-gray-900">{{ $sc['supplier']->name }}</p>
                        @if($sc['return_rate'] > 10)
                            <span class="text-xs font-bold text-rose-600">{{ $sc['return_rate'] }}% returns</span>
                        @elseif($sc['return_rate'] > 0)
                            <span class="text-xs font-bold text-amber-600">{{ $sc['return_rate'] }}% returns</span>
                        @else
                            <span class="text-xs font-bold text-emerald-600">0% returns</span>
                        @endif
                    </div>
                    <div class="grid grid-cols-3 gap-2">
                        <div><p class="text-[10px] text-gray-400">Supplied</p><p class="text-sm font-bold text-gray-900">{{ $sc['total_supplied'] }}</p></div>
                        <div><p class="text-[10px] text-gray-400">Sold</p><p class="text-sm font-bold text-emerald-600">{{ $sc['total_sold'] }}</p></div>
                        <div><p class="text-[10px] text-gray-400">Avg Cost</p><p class="text-sm font-bold text-gray-900">₹{{ number_format($sc['avg_cost_per_unit']) }}</p></div>
                    </div>
                </a>
                @endforeach
            </div>
            {{-- Desktop table --}}
            <div class="hidden md:block overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b border-gray-100">
                            <th class="pb-2.5 text-left text-[10px] font-bold uppercase tracking-wider text-gray-400">Supplier</th>
                            <th class="pb-2.5 text-right text-[10px] font-bold uppercase tracking-wider text-gray-400">Supplied</th>
                            <th class="pb-2.5 text-right text-[10px] font-bold uppercase tracking-wider text-gray-400">Sold</th>
                            <th class="pb-2.5 text-right text-[10px] font-bold uppercase tracking-wider text-gray-400">Returns</th>
                            <th class="pb-2.5 text-right text-[10px] font-bold uppercase tracking-wider text-gray-400">Return Rate</th>
                            <th class="pb-2.5 text-right text-[10px] font-bold uppercase tracking-wider text-gray-400">Avg Cost</th>
                            <th class="pb-2.5 text-center text-[10px] font-bold uppercase tracking-wider text-gray-400">Batches</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-50">
                        @foreach($supplier_comparison as $sc)
                        <tr class="hover:bg-gray-50/50 transition-colors">
                            <td class="py-2.5"><a href="{{ route('suppliers.show', $sc['supplier']) }}" class="font-medium text-brand-600 hover:text-brand-700">{{ $sc['supplier']->name }}</a></td>
                            <td class="py-2.5 text-right text-gray-600">{{ $sc['total_supplied'] }}</td>
                            <td class="py-2.5 text-right text-emerald-600 font-medium">{{ $sc['total_sold'] }}</td>
                            <td class="py-2.5 text-right text-amber-600">{{ $sc['returns'] }}</td>
                            <td class="py-2.5 text-right">
                                @if($sc['return_rate'] > 10)
                                    <span class="font-bold text-rose-600">{{ $sc['return_rate'] }}%</span>
                                @elseif($sc['return_rate'] > 0)
                                    <span class="font-bold text-amber-600">{{ $sc['return_rate'] }}%</span>
                                @else
                                    <span class="font-bold text-emerald-600">0%</span>
                                @endif
                            </td>
                            <td class="py-2.5 text-right text-gray-600">₹{{ number_format($sc['avg_cost_per_unit']) }}</td>
                            <td class="py-2.5 text-center"><x-badge variant="info" size="xs">{{ $sc['batches_count'] }}</x-badge></td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </x-card>
        @endif

        <!-- Sale History (from order_items, paginated) -->
        <x-card title="Sales / Order History">
            @if($recent_orders->isEmpty())
                <x-empty-state icon="receipt" title="No sales yet" description="Record a sale to see order history." />
            @else
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="border-b border-gray-100">
                                <th class="pb-2.5 text-left text-[10px] font-bold uppercase tracking-wider text-gray-400">Order</th>
                                <th class="pb-2.5 text-left text-[10px] font-bold uppercase tracking-wider text-gray-400">Platform</th>
                                <th class="pb-2.5 text-left text-[10px] font-bold uppercase tracking-wider text-gray-400">Customer</th>
                                <th class="pb-2.5 text-right text-[10px] font-bold uppercase tracking-wider text-gray-400">Qty</th>
                                <th class="pb-2.5 text-right text-[10px] font-bold uppercase tracking-wider text-gray-400">Amount</th>
                                <th class="pb-2.5 text-center text-[10px] font-bold uppercase tracking-wider text-gray-400">Status</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-50" id="orderHistoryBody">
                            @include('products._sale-rows', ['recent_orders' => $recent_orders])
                        </tbody>
                    </table>
                </div>

                @if($recent_orders->hasMorePages())
                <div class="mt-4 text-center" id="loadMoreOrdersWrap">
                    <button type="button" id="loadMoreOrdersBtn" class="inline-flex items-center gap-2 rounded-xl border border-gray-300 bg-white px-5 py-2.5 text-sm font-medium text-gray-700 shadow-sm hover:bg-gray-50 transition-all">
                        <i class="bi bi-arrow-down text-xs"></i> Load more sales
                    </button>
                </div>
                @endif
            @endif
        </x-card>
    </div>
</div>
@endsection

@section('scripts')
<script>
let orderPage = {{ $recent_orders->currentPage() }};

// For supplier batch orders page
if (typeof currentPage !== 'undefined') return; // skip if supplier page script already loaded

document.getElementById('loadMoreOrdersBtn')?.addEventListener('click', function() {
    const btn = this;
    btn.disabled = true;
    btn.innerHTML = '<i class="bi bi-hourglass-split text-xs"></i> Loading...';
    orderPage++;

    fetch('{{ route("products.summary", $product) }}?page=' + orderPage)
        .then(r => r.json())
        .then(data => {
            const tbody = document.getElementById('orderHistoryBody');
            if (data.html) {
                tbody.insertAdjacentHTML('beforeend', data.html);
            }
            if (!data.hasMore) {
                document.getElementById('loadMoreOrdersWrap')?.remove();
            } else {
                btn.disabled = false;
                btn.innerHTML = '<i class="bi bi-arrow-down text-xs"></i> Load more sales';
            }
        })
        .catch(() => { btn.disabled = false; btn.innerHTML = '<i class="bi bi-arrow-down text-xs"></i> Load more sales'; });
});
</script>
@endsection
