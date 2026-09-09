@extends('layouts.app')
@section('title', 'Dashboard')
@section('subtitle', 'Overview of your business performance')

@section('content')

@php
    $s = $summary;
@endphp

{{-- ═══════════════════════════════════════════════════════════
     TOP — The Big Numbers (3 cards)
     ═══════════════════════════════════════════════════════════ --}}
<div class="grid grid-cols-1 gap-4 sm:grid-cols-3 mb-6">
    {{-- Net Profit --}}
    <div class="rounded-2xl border-2 {{ $s['net_profit'] >= 0 ? 'border-emerald-200 bg-emerald-50' : 'border-rose-200 bg-rose-50' }} p-5">
        <div class="flex items-center gap-2 mb-1">
            <div class="w-8 h-8 rounded-lg {{ $s['net_profit'] >= 0 ? 'bg-emerald-100' : 'bg-rose-100' }} flex items-center justify-center">
                <i class="bi bi-{{ $s['net_profit'] >= 0 ? 'graph-up-arrow text-emerald-600' : 'graph-down-arrow text-rose-600' }}"></i>
            </div>
            <p class="text-xs font-bold uppercase tracking-wider {{ $s['net_profit'] >= 0 ? 'text-emerald-700' : 'text-rose-700' }}">{{ $s['net_profit'] >= 0 ? 'Profit hai!' : 'Nuksan hai' }}</p>
        </div>
        <p class="text-3xl font-bold {{ $s['net_profit'] >= 0 ? 'text-emerald-700' : 'text-rose-700' }}">{{ $s['net_profit'] >= 0 ? '+' : '' }}₹{{ number_format($s['net_profit']) }}</p>
        <p class="text-xs text-gray-500 mt-1">= Revenue ₹{{ number_format($s['total_revenue']) }} − Costs ₹{{ number_format($s['total_cogs'] + $s['total_charges'] + $s['return_charges'] + $s['total_expenses'] + $s['missing_cost']) }}</p>
    </div>

    {{-- Revenue --}}
    <div class="rounded-2xl border border-emerald-200 bg-emerald-50 p-5">
        <div class="flex items-center gap-2 mb-1">
            <div class="w-8 h-8 rounded-lg bg-emerald-100 flex items-center justify-center">
                <i class="bi bi-cash-stack text-emerald-600"></i>
            </div>
            <p class="text-xs font-bold uppercase tracking-wider text-emerald-700">Revenue (Kitna mila)</p>
        </div>
        <p class="text-3xl font-bold text-emerald-700">₹{{ number_format($s['total_revenue']) }}</p>
        <div class="text-xs text-gray-500 mt-1 space-y-0.5">
            <p>Collected: ₹{{ number_format($s['total_revenue']) }} ({{ $s['successful_orders'] }} orders)</p>
            @if($s['pending_revenue'] > 0)
            <p>Pending: ₹{{ number_format($s['pending_revenue']) }} (abhi aana baaki)</p>
            @endif
        </div>
    </div>

    {{-- Cash Position --}}
    <div class="rounded-2xl border {{ ($s['cash_position'] >= 0) ? 'border-emerald-200 bg-emerald-50' : 'border-rose-200 bg-rose-50' }} p-5">
        <div class="flex items-center gap-2 mb-1">
            <div class="w-8 h-8 rounded-lg {{ ($s['cash_position'] >= 0) ? 'bg-emerald-100' : 'bg-rose-100' }} flex items-center justify-center">
                <i class="bi bi-wallet2 {{ ($s['cash_position'] >= 0) ? 'text-emerald-600' : 'text-rose-600' }}"></i>
            </div>
            <p class="text-xs font-bold uppercase tracking-wider {{ ($s['cash_position'] >= 0) ? 'text-emerald-700' : 'text-rose-700' }}">Cash Balance (Jeb mein)</p>
        </div>
        <p class="text-3xl font-bold {{ ($s['cash_position'] >= 0) ? 'text-emerald-700' : 'text-rose-700' }}">{{ ($s['cash_position'] >= 0) ? '+' : '' }}₹{{ number_format($s['cash_position']) }}</p>
        <div class="text-xs text-gray-500 mt-1 space-y-0.5">
            <p>In: ₹{{ number_format($s['total_payments']) }} | Out: ₹{{ number_format($s['total_invested'] + $s['total_expenses']) }}</p>
            @if($s['inventory_value'] > 0)
            <p>📦 Stock value: ₹{{ number_format($s['inventory_value']) }}</p>
            @endif
        </div>
    </div>
</div>

{{-- ═══════════════════════════════════════════════════════════
     SECOND ROW — Quick Counts
     ═══════════════════════════════════════════════════════════ --}}
<div class="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-6 mb-6">
    <a href="{{ route('products.index') }}" class="rounded-2xl border border-gray-100 bg-white p-4 text-center shadow-sm hover:shadow-md hover:-translate-y-0.5 transition-all">
        <p class="text-2xl font-bold text-gray-900">{{ $s['total_products'] }}</p>
        <p class="text-xs text-gray-500 font-medium mt-1">Products</p>
    </a>
    <div class="rounded-2xl border border-gray-100 bg-white p-4 text-center shadow-sm">
        <p class="text-2xl font-bold text-amber-600">{{ $s['pending_orders'] }}</p>
        <p class="text-xs text-gray-500 font-medium mt-1">Pending Orders</p>
    </div>
    <div class="rounded-2xl border border-gray-100 bg-white p-4 text-center shadow-sm">
        <p class="text-2xl font-bold text-emerald-600">{{ $s['successful_orders'] }}</p>
        <p class="text-xs text-gray-500 font-medium mt-1">Successful</p>
    </div>
    <div class="rounded-2xl border border-gray-100 bg-white p-4 text-center shadow-sm">
        <p class="text-2xl font-bold text-rose-600">{{ $s['returned_orders'] }}</p>
        <p class="text-xs text-gray-500 font-medium mt-1">Returns</p>
    </div>
    <div class="rounded-2xl border border-gray-100 bg-white p-4 text-center shadow-sm">
        <p class="text-2xl font-bold text-gray-400">{{ $s['missing_orders'] }}</p>
        <p class="text-xs text-gray-500 font-medium mt-1">Missing</p>
    </div>
    @if($s['low_stock_count'] > 0)
    <a href="{{ route('products.low-stock') }}" class="rounded-2xl border border-rose-200 bg-rose-50 p-4 text-center shadow-sm hover:shadow-md transition-all">
        <p class="text-2xl font-bold text-rose-600">{{ $s['low_stock_count'] }}</p>
        <p class="text-xs text-rose-600 font-medium mt-1">Low Stock ⚠️</p>
    </a>
    @else
    <div class="rounded-2xl border border-emerald-200 bg-emerald-50 p-4 text-center shadow-sm">
        <p class="text-2xl font-bold text-emerald-600">✓</p>
        <p class="text-xs text-emerald-600 font-medium mt-1">Stock OK</p>
    </div>
    @endif
</div>

{{-- ═══════════════════════════════════════════════════════════
     COST SUMMARY — Quick glance
     ═══════════════════════════════════════════════════════════ --}}
<div class="rounded-2xl border border-gray-100 bg-white p-5 shadow-sm mb-6">
    <h3 class="text-sm font-bold text-gray-900 mb-3">Cost Summary — Kahan gaya paisa</h3>
    <div class="grid grid-cols-2 gap-4 sm:grid-cols-4">
        <div>
            <p class="text-xs text-gray-500">COGS (bikne wale maal ki keemat)</p>
            <p class="text-lg font-bold text-rose-600">₹{{ number_format($s['total_cogs']) }}</p>
        </div>
        <div>
            <p class="text-xs text-gray-500">Platform charges (Amazon/Flipkart)</p>
            <p class="text-lg font-bold text-rose-600">₹{{ number_format($s['total_charges']) }}</p>
        </div>
        <div>
            <p class="text-xs text-gray-500">Expenses (rent, packaging, bills)</p>
            <p class="text-lg font-bold text-rose-600">₹{{ number_format($s['total_expenses']) }}</p>
        </div>
        <div>
            <p class="text-xs text-gray-500">Missing items (nuksan)</p>
            <p class="text-lg font-bold text-rose-600">₹{{ number_format($s['missing_cost']) }}</p>
        </div>
    </div>
    <div class="mt-3 pt-3 border-t border-gray-100 flex justify-between items-center">
        <span class="text-sm font-semibold text-gray-700">Total Kharche (sab mila ke)</span>
        <span class="text-lg font-bold text-gray-900">₹{{ number_format($s['total_cogs'] + $s['total_charges'] + $s['return_charges'] + $s['total_expenses'] + $s['missing_cost']) }}</span>
    </div>
    <a href="{{ route('accounting.index') }}" class="mt-2 inline-block text-xs font-semibold text-brand-600 hover:text-brand-700">View full Accounting →</a>
</div>

<div class="grid grid-cols-1 gap-6 lg:grid-cols-2">
    <!-- Low Stock -->
    <x-card title="Low Stock Alert" subtitle="Products below reorder threshold">
        <x-slot:action>
            <a href="{{ route('products.low-stock') }}" class="inline-flex items-center min-h-[44px] px-2 -mr-2 text-xs font-semibold text-brand-600 hover:text-brand-700 transition-colors">View all →</a>
        </x-slot:action>

        @if($s['low_stock_products']->isEmpty())
            <x-empty-state icon="box-seam" title="All stocked up" description="No products below reorder threshold." />
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b border-gray-100">
                            <th class="pb-2.5 text-left text-[10px] font-bold uppercase tracking-wider text-gray-400">Product</th>
                            <th class="pb-2.5 text-left text-[10px] font-bold uppercase tracking-wider text-gray-400">SKU</th>
                            <th class="pb-2.5 text-right text-[10px] font-bold uppercase tracking-wider text-gray-400">Stock</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-50">
                        @foreach($s['low_stock_products'] as $product)
                        <tr class="hover:bg-gray-50/50 transition-colors">
                            <td class="py-3">
                                <a href="{{ route('products.show', $product) }}" class="font-semibold text-gray-900 hover:text-brand-600 transition-colors">{{ $product->name }}</a>
                            </td>
                            <td class="py-3"><code class="text-xs bg-gray-100 px-1.5 py-0.5 rounded-md text-gray-600">{{ $product->sku }}</code></td>
                            <td class="py-3 text-right">
                                @if($product->stock_quantity === 0)
                                    <x-badge variant="danger">Out of Stock</x-badge>
                                @else
                                    <x-badge variant="warning">{{ $product->stock_quantity }} left</x-badge>
                                @endif
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </x-card>

    <!-- Pending Returns -->
    <x-card title="Pending Return Checks" subtitle="Orders awaiting status verification">
        <x-slot:action>
            <a href="{{ route('orders.reminders') }}" class="inline-flex items-center min-h-[44px] px-2 -mr-2 text-xs font-semibold text-brand-600 hover:text-brand-700 transition-colors">View all →</a>
        </x-slot:action>

        @if($s['orders_needing_reminder']->isEmpty())
            <x-empty-state icon="bell-slash" title="All clear" description="No orders pending return check." />
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b border-gray-100">
                            <th class="pb-2.5 text-left text-[10px] font-bold uppercase tracking-wider text-gray-400">Order</th>
                            <th class="pb-2.5 text-left text-[10px] font-bold uppercase tracking-wider text-gray-400">Product</th>
                            <th class="pb-2.5 text-left text-[10px] font-bold uppercase tracking-wider text-gray-400">Platform</th>
                            <th class="pb-2.5 text-right text-[10px] font-bold uppercase tracking-wider text-gray-400">Shipped</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-50">
                        @foreach($s['orders_needing_reminder'] as $order)
                        <tr class="hover:bg-gray-50/50 transition-colors">
                            <td class="py-3">
                                <a href="{{ route('orders.show', $order) }}" class="font-semibold text-gray-900 hover:text-brand-600 transition-colors">{{ $order->order_number }}</a>
                            </td>
                            <td class="py-3 text-gray-600">{{ $order->items->pluck('product.name')->implode(', ') }}</td>
                            <td class="py-3"><x-badge variant="info">{{ $order->platform->name }}</x-badge></td>
                            <td class="py-3 text-right text-gray-500 text-xs">{{ $order->shipped_at->diffForHumans() }}</td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </x-card>
</div>
@endsection
