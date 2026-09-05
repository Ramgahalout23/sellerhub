@extends('layouts.app')
@section('title', 'Products')
@section('subtitle', $products->total() . ' products in inventory')

@section('actions')
<a href="{{ route('products.low-stock') }}" class="touch-target inline-flex items-center gap-1.5 rounded-xl border border-gray-300 bg-white px-3 py-2 text-sm font-medium text-gray-700 shadow-sm hover:bg-gray-50 transition-all">
    <i class="bi bi-exclamation-triangle text-amber-500"></i> <span class="hidden sm:inline">Low Stock</span>
</a>
<a href="{{ route('products.create') }}" class="touch-target inline-flex items-center gap-1.5 rounded-xl bg-brand-600 px-3 py-2 text-sm font-semibold text-white shadow-lg shadow-brand-200 hover:bg-brand-700 transition-all">
    <i class="bi bi-plus-lg"></i> <span class="hidden sm:inline">Add Product</span><span class="sm:hidden">Add</span>
</a>
@endsection

@section('content')
<x-card padding="false">
    <x-slot:action>
        <form class="flex gap-2 flex-wrap" method="GET">
            <input type="text" name="search" class="rounded-lg border border-gray-200 bg-gray-50 px-3 py-2 text-sm placeholder:text-gray-400 focus:border-brand-500 focus:ring-1 focus:ring-brand-500 focus:outline-none flex-1 min-w-[120px]" placeholder="Search products..." value="{{ request('search') }}">
            <select name="supplier_id" class="rounded-lg border border-gray-200 bg-gray-50 px-3 py-2 text-sm focus:border-brand-500 focus:ring-1 focus:ring-brand-500 focus:outline-none">
                <option value="">All Suppliers</option>
                @foreach($suppliers as $s)
                    <option value="{{ $s->id }}" {{ request('supplier_id') == $s->id ? 'selected' : '' }}>{{ $s->name }}</option>
                @endforeach
            </select>
            <button class="touch-target rounded-lg bg-brand-600 px-3 py-2 text-sm font-medium text-white hover:bg-brand-700 transition-colors"><i class="bi bi-search"></i></button>
        </form>
    </x-slot:action>

    {{-- ═══════ DESKTOP TABLE ═══════ --}}
    <div class="hidden md:block overflow-x-auto">
        <table class="w-full text-sm">
            <thead>
                <tr class="border-b border-gray-100">
                    <th class="px-5 py-3 text-left text-[10px] font-bold uppercase tracking-wider text-gray-400">Product</th>
                    <th class="px-5 py-3 text-left text-[10px] font-bold uppercase tracking-wider text-gray-400">SKU</th>
                    <th class="px-5 py-3 text-right text-[10px] font-bold uppercase tracking-wider text-gray-400">Cost</th>
                    <th class="px-5 py-3 text-right text-[10px] font-bold uppercase tracking-wider text-gray-400">Sell</th>
                    <th class="px-5 py-3 text-right text-[10px] font-bold uppercase tracking-wider text-gray-400">Stock</th>
                    <th class="px-5 py-3 text-left text-[10px] font-bold uppercase tracking-wider text-gray-400">Suppliers</th>
                    <th class="px-5 py-3 text-right text-[10px] font-bold uppercase tracking-wider text-gray-400">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-50">
                @forelse($products as $product)
                <tr class="hover:bg-gray-50/50 transition-colors">
                    <td class="px-5 py-3.5">
                        <a href="{{ route('products.show', $product) }}" class="font-semibold text-gray-900 hover:text-brand-600 transition-colors">{{ $product->name }}</a>
                    </td>
                    <td class="px-5 py-3.5"><code class="text-xs bg-gray-100 px-1.5 py-0.5 rounded-md text-gray-600">{{ $product->sku }}</code></td>
                    <td class="px-5 py-3.5 text-right text-gray-600">₹{{ number_format($product->cost_price) }}</td>
                    <td class="px-5 py-3.5 text-right font-medium text-gray-900">₹{{ number_format($product->selling_price) }}</td>
                    <td class="px-5 py-3.5 text-right">
                        @if($product->stock_quantity <= 0)
                            <x-badge variant="danger">0</x-badge>
                        @elseif($product->stock_quantity <= $product->reorder_threshold)
                            <x-badge variant="warning">{{ $product->stock_quantity }}</x-badge>
                        @else
                            <x-badge variant="success">{{ $product->stock_quantity }}</x-badge>
                        @endif
                    </td>
                    <td class="px-5 py-3.5">
                        @foreach($product->suppliers->take(2) as $s)
                            <x-badge variant="outline">{{ $s->name }}</x-badge>
                        @endforeach
                    </td>
                    <td class="px-5 py-3.5 text-right">
                        <div class="flex items-center justify-end gap-1">
                            <a href="{{ route('products.show', $product) }}" class="p-1.5 rounded-lg text-gray-400 hover:text-brand-600 hover:bg-brand-50 transition-all"><i class="bi bi-eye text-sm"></i></a>
                            <a href="{{ route('products.edit', $product) }}" class="p-1.5 rounded-lg text-gray-400 hover:text-gray-600 hover:bg-gray-100 transition-all"><i class="bi bi-pencil text-sm"></i></a>
                        </div>
                    </td>
                </tr>
                @empty
                <tr><td colspan="7"><x-empty-state icon="box-seam" title="No products yet" description="Add your first product to get started." /></td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{-- ═══════ MOBILE CARDS ═══════ --}}
    <div class="md:hidden divide-y divide-gray-100">
        @forelse($products as $product)
        <a href="{{ route('products.show', $product) }}" class="block p-4 hover:bg-gray-50 transition-colors">
            <div class="flex items-start justify-between gap-3">
                <div class="min-w-0 flex-1">
                    <p class="font-bold text-gray-900 text-sm leading-tight">{{ $product->name }}</p>
                    <p class="text-[11px] text-gray-400 mt-0.5 font-mono">{{ $product->sku }}</p>
                </div>
                <div class="text-right shrink-0">
                    <p class="font-bold text-gray-900 text-sm">₹{{ number_format($product->selling_price) }}</p>
                    <p class="text-[11px] text-gray-400">cost ₹{{ number_format($product->cost_price) }}</p>
                </div>
            </div>
            <div class="flex items-center gap-2 mt-2 flex-wrap">
                @if($product->stock_quantity <= 0)
                    <x-badge variant="danger">Out of stock</x-badge>
                @elseif($product->stock_quantity <= $product->reorder_threshold)
                    <x-badge variant="warning">Stock: {{ $product->stock_quantity }}</x-badge>
                @else
                    <x-badge variant="success">Stock: {{ $product->stock_quantity }}</x-badge>
                @endif
                @foreach($product->suppliers->take(2) as $s)
                    <x-badge variant="outline" class="text-[10px]">{{ $s->name }}</x-badge>
                @endforeach
            </div>
        </a>
        @empty
            <div class="p-6">
                <x-empty-state icon="box-seam" title="No products yet" description="Add your first product to get started." />
            </div>
        @endforelse
    </div>

    @if($products->hasPages())
        <div class="border-t border-gray-100 px-5 py-3">
            {{ $products->withQueryString()->links() }}
        </div>
    @endif
</x-card>
@endsection
