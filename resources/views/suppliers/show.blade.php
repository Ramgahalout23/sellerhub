@extends('layouts.app')
@section('title', $supplier->name)
@section('subtitle', 'Supplier details and purchase history')

@section('actions')
<a href="{{ route('batch-orders.create') }}?supplier_id={{ $supplier->id }}" class="touch-target inline-flex items-center gap-1.5 rounded-xl bg-brand-600 px-3 py-2 text-sm font-semibold text-white shadow-lg shadow-brand-200 hover:bg-brand-700 transition-all">
    <i class="bi bi-plus-lg"></i> <span class="hidden sm:inline">New Batch Order</span><span class="sm:hidden">New Batch</span>
</a>
<a href="{{ route('suppliers.edit', $supplier) }}" class="touch-target inline-flex items-center gap-1.5 rounded-xl border border-gray-300 bg-white px-3 py-2 text-sm font-medium text-gray-700 shadow-sm hover:bg-gray-50 transition-all">
    <i class="bi bi-pencil text-xs"></i> <span class="hidden sm:inline">Edit</span>
</a>
@endsection

@section('content')
<!-- Summary Stats -->
<div class="grid grid-cols-2 gap-3 lg:gap-4 mb-6">
    <x-stat-card title="Total Purchased" value="₹{{ number_format($totalPurchased) }}" icon="cash-stack" color="primary" />
    <x-stat-card title="Batch Orders" value="{{ $totalBatchOrders }}" icon="cart-plus" color="info" />
    <x-stat-card title="Products" value="{{ $supplier->products->count() }}" icon="box-seam" color="success" />
    <x-stat-card title="Total Units" value="{{ $totalUnits }}" icon="boxes" color="purple" />
</div>

<div class="grid grid-cols-1 gap-6 lg:grid-cols-4">
    <!-- Left Column -->
    <div class="lg:col-span-1 space-y-4">
        <x-card title="Supplier Details">
            <div class="space-y-3">
                <div><p class="text-[10px] font-bold uppercase tracking-wider text-gray-400 mb-0.5">Name</p><p class="text-sm font-medium text-gray-900">{{ $supplier->name }}</p></div>
                <div><p class="text-[10px] font-bold uppercase tracking-wider text-gray-400 mb-0.5">Contact Person</p><p class="text-sm text-gray-700">{{ $supplier->contact_person ?? '—' }}</p></div>
                <div><p class="text-[10px] font-bold uppercase tracking-wider text-gray-400 mb-0.5">Phone</p><p class="text-sm text-gray-700">{{ $supplier->phone ?? '—' }}</p></div>
                <div><p class="text-[10px] font-bold uppercase tracking-wider text-gray-400 mb-0.5">Email</p><p class="text-sm text-gray-700">{{ $supplier->email ?? '—' }}</p></div>
                <div><p class="text-[10px] font-bold uppercase tracking-wider text-gray-400 mb-0.5">Address</p><p class="text-sm text-gray-700">{{ $supplier->address ?? '—' }}</p></div>
                <div><p class="text-[10px] font-bold uppercase tracking-wider text-gray-400 mb-0.5">Status</p>
                    @if($supplier->is_active)<x-badge variant="success">Active</x-badge>@else<x-badge variant="default">Inactive</x-badge>@endif
                </div>
            </div>
        </x-card>

        <!-- Quick Actions -->
        <x-card title="Quick Actions">
            <div class="space-y-2">
                <a href="{{ route('batch-orders.create') }}?supplier_id={{ $supplier->id }}" class="flex items-center gap-2 rounded-lg px-3 py-2 text-sm font-medium text-gray-700 hover:bg-brand-50 hover:text-brand-700 transition-colors">
                    <i class="bi bi-cart-plus text-brand-500"></i> Create Batch Order
                </a>
                <a href="{{ route('suppliers.edit', $supplier) }}" class="flex items-center gap-2 rounded-lg px-3 py-2 text-sm font-medium text-gray-700 hover:bg-gray-100 transition-colors">
                    <i class="bi bi-pencil text-gray-400"></i> Edit Supplier
                </a>
            </div>
        </x-card>
    </div>

    <!-- Right Column -->
    <div class="lg:col-span-3 space-y-4">
        <!-- Products Supplied -->
        <x-card title="Products Supplied" subtitle="{{ $supplier->products->count() }} products from this supplier">
            @if($supplier->products->isEmpty())
                <x-empty-state icon="box-seam" title="No products" description="No products from this supplier yet." />
            @else
                {{-- Mobile cards --}}
                <div class="md:hidden space-y-3">
                    @foreach($supplier->products as $product)
                    @php
                        $itemsForProduct = $product->batchOrderItems->where('batchOrder.supplier_id', $supplier->id);
                        $totalBought = $itemsForProduct->sum('quantity');
                        $totalSpent = $itemsForProduct->sum('total_cost');
                        $batchCount = $itemsForProduct->pluck('batch_order_id')->unique()->count();
                    @endphp
                    <a href="{{ route('products.show', $product) }}" class="block p-3 rounded-xl border border-gray-100 hover:bg-gray-50">
                        <div class="flex items-start justify-between">
                            <div class="min-w-0 flex-1">
                                <p class="text-sm font-bold text-gray-900 truncate">{{ $product->name }}</p>
                                <p class="text-[11px] text-gray-400 font-mono">{{ $product->sku }}</p>
                            </div>
                            <p class="text-sm font-bold text-gray-900 shrink-0 ml-2">₹{{ number_format($totalSpent) }}</p>
                        </div>
                        <div class="flex items-center gap-2 mt-2 flex-wrap">
                            <x-badge :variant="$product->stock_quantity <= 0 ? 'danger' : ($product->stock_quantity <= $product->reorder_threshold ? 'warning' : 'success')">Stock: {{ $product->stock_quantity }}</x-badge>
                            <x-badge variant="info" size="xs">{{ $batchCount }} {{ Str::plural('batch', $batchCount) }}</x-badge>
                            <span class="text-[11px] text-gray-400">{{ $totalBought }} units, ₹{{ number_format($totalSpent) }}</span>
                        </div>
                    </a>
                    @endforeach
                </div>
                {{-- Desktop table --}}
                <div class="hidden md:block overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="border-b border-gray-100">
                                <th class="pb-2.5 text-left text-[10px] font-bold uppercase tracking-wider text-gray-400">Product</th>
                                <th class="pb-2.5 text-left text-[10px] font-bold uppercase tracking-wider text-gray-400">SKU</th>
                                <th class="pb-2.5 text-right text-[10px] font-bold uppercase tracking-wider text-gray-400">Last Price</th>
                                <th class="pb-2.5 text-right text-[10px] font-bold uppercase tracking-wider text-gray-400">Stock</th>
                                <th class="pb-2.5 text-center text-[10px] font-bold uppercase tracking-wider text-gray-400">Batches</th>
                                <th class="pb-2.5 text-right text-[10px] font-bold uppercase tracking-wider text-gray-400">Total Purchased</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-50">
                            @foreach($supplier->products as $product)
                            @php
                                $itemsForProduct = $product->batchOrderItems->where('batchOrder.supplier_id', $supplier->id);
                                $totalBought = $itemsForProduct->sum('quantity');
                                $totalSpent = $itemsForProduct->sum('total_cost');
                                $batchCount = $itemsForProduct->pluck('batch_order_id')->unique()->count();
                            @endphp
                            <tr class="hover:bg-gray-50/50 transition-colors">
                                <td class="py-3">
                                    <a href="{{ route('products.show', $product) }}" class="font-medium text-brand-600 hover:text-brand-700">{{ $product->name }}</a>
                                </td>
                                <td class="py-3"><code class="text-xs bg-gray-100 px-1.5 py-0.5 rounded-md text-gray-600">{{ $product->sku }}</code></td>
                                <td class="py-3 text-right text-gray-600">₹{{ number_format($product->pivot->last_known_price ?? $product->cost_price) }}</td>
                                <td class="py-3 text-right">
                                    <x-badge :variant="$product->stock_quantity <= 0 ? 'danger' : ($product->stock_quantity <= $product->reorder_threshold ? 'warning' : 'success')">{{ $product->stock_quantity }}</x-badge>
                                </td>
                                <td class="py-3 text-center">
                                    <x-badge variant="info" size="sm">{{ $batchCount }} {{ Str::plural('batch', $batchCount) }}</x-badge>
                                </td>
                                <td class="py-3 text-right">
                                    <div class="text-sm font-medium text-gray-900">{{ $totalBought }} units</div>
                                    <div class="text-xs text-gray-500">₹{{ number_format($totalSpent) }}</div>
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </x-card>

        <!-- Purchase History (paginated) -->
        <x-card title="Purchase History" subtitle="{{ $totalBatchOrders }} batch orders — ₹{{ number_format($totalPurchased) }} total">
            @if($batchOrders->isEmpty())
                <x-empty-state icon="cart-plus" title="No batch orders" description="Create your first batch order to start tracking purchases." />
            @else
                <div id="batchOrdersList" class="space-y-4">
                    @include('suppliers._batch-orders-list', ['batchOrders' => $batchOrders])
                </div>

                {{-- Load More Button --}}
                @if($batchOrders->hasMorePages())
                <div class="mt-4 text-center" id="loadMoreWrap">
                    <button type="button" id="loadMoreBtn" class="inline-flex items-center gap-2 rounded-xl border border-gray-300 bg-white px-5 py-2.5 text-sm font-medium text-gray-700 shadow-sm hover:bg-gray-50 hover:border-gray-400 transition-all">
                        <i class="bi bi-arrow-down text-xs"></i>
                        <span id="loadMoreText">Load older orders</span>
                        <span class="text-xs text-gray-400">({{ $totalBatchOrders - $batchOrders->count() }} more)</span>
                    </button>
                </div>
                @endif

                {{-- Laravel Pagination (as fallback) --}}
                @if($batchOrders->hasPages())
                    <div class="border-t border-gray-100 px-5 py-3 mt-4 hidden">
                        {{ $batchOrders->withQueryString()->links() }}
                    </div>
                @endif
            @endif
        </x-card>
    </div>
</div>

@endsection

@section('scripts')
<script>
let currentPage = {{ $batchOrders->currentPage() }};
let hasMore = {{ $batchOrders->hasMorePages() ? 'true' : 'false' }};

document.getElementById('loadMoreBtn')?.addEventListener('click', function() {
    const btn = this;
    const text = document.getElementById('loadMoreText');
    const originalText = text.textContent;
    text.textContent = 'Loading...';
    btn.disabled = true;

    currentPage++;

    fetch('{{ route("suppliers.batch-orders-more", $supplier) }}?page=' + currentPage)
        .then(r => r.json())
        .then(data => {
            // Append new items to the list
            const list = document.getElementById('batchOrdersList');
            list.insertAdjacentHTML('beforeend', data.html);

            // Update state
            hasMore = data.hasMore;

            if (!hasMore) {
                document.getElementById('loadMoreWrap')?.remove();
            } else {
                text.textContent = originalText;
                btn.disabled = false;
                // Update count
                const countSpan = btn.querySelector('.text-xs');
                const remaining = parseInt(countSpan?.textContent?.match(/\d+/)?.[0] || 0) - 5;
                if (countSpan && remaining > 0) {
                    countSpan.textContent = '(' + remaining + ' more)';
                } else {
                    document.getElementById('loadMoreWrap')?.remove();
                }
            }
        })
        .catch(() => {
            text.textContent = originalText;
            btn.disabled = false;
        });
});
</script>
@endsection
