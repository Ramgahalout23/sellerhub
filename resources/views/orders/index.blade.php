@extends('layouts.app')
@section('title', 'Orders')
@section('subtitle', $orders->total() . ' orders total')

@section('actions')
<a href="{{ route('orders.create') }}" class="inline-flex items-center gap-1.5 rounded-xl bg-brand-600 px-3.5 py-2 text-sm font-semibold text-white shadow-lg shadow-brand-200 hover:bg-brand-700 transition-all touch-target">
    <i class="bi bi-plus-lg"></i> <span class="hidden sm:inline">New Order</span><span class="sm:hidden">New</span>
</a>
@endsection

@section('content')
<x-card padding="false">
    <x-slot:action>
        <form class="flex gap-2 flex-wrap" method="GET">
            <input type="text" name="search" class="rounded-lg border border-gray-200 bg-gray-50 px-3 py-2 text-sm placeholder:text-gray-400 focus:border-brand-500 focus:ring-1 focus:ring-brand-500 focus:outline-none flex-1 min-w-[120px] w-full sm:w-auto" placeholder="Search order..." value="{{ request('search') }}">
            <select name="status" class="rounded-lg border border-gray-200 bg-gray-50 px-3 py-2 text-sm focus:border-brand-500 focus:ring-1 focus:ring-brand-500 focus:outline-none">
                <option value="">All Status</option>
                @foreach(['pending','successful','customer_return','rto','missing'] as $s)
                    <option value="{{ $s }}" {{ request('status') === $s ? 'selected' : '' }}>{{ ucfirst(str_replace('_', ' ', $s)) }}</option>
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
                    <th class="px-5 py-3 text-left text-[10px] font-bold uppercase tracking-wider text-gray-400">Order</th>
                    <th class="px-5 py-3 text-left text-[10px] font-bold uppercase tracking-wider text-gray-400">Products</th>
                    <th class="px-5 py-3 text-left text-[10px] font-bold uppercase tracking-wider text-gray-400">Platform</th>
                    <th class="px-5 py-3 text-left text-[10px] font-bold uppercase tracking-wider text-gray-400">Customer</th>
                    <th class="px-5 py-3 text-right text-[10px] font-bold uppercase tracking-wider text-gray-400">Amount</th>
                    <th class="px-5 py-3 text-center text-[10px] font-bold uppercase tracking-wider text-gray-400">Status</th>
                    <th class="px-5 py-3 text-right text-[10px] font-bold uppercase tracking-wider text-gray-400">Date</th>
                    <th class="px-5 py-3 text-right text-[10px] font-bold uppercase tracking-wider text-gray-400"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-50">
                @forelse($orders as $order)
                <tr class="hover:bg-gray-50/50 transition-colors">
                    <td class="px-5 py-3.5">
                        <a href="{{ route('orders.show', $order) }}" class="font-semibold text-gray-900 hover:text-brand-600 transition-colors">{{ $order->order_number ?? '#' . $order->id }}</a>
                    </td>
                    <td class="px-5 py-3.5">
                        <div class="text-xs text-gray-600">
                            @foreach($order->items->take(3) as $item)
                                <div class="truncate max-w-[180px]">{{ $item->product->name }} × {{ $item->quantity }}</div>
                            @endforeach
                        </div>
                    </td>
                    <td class="px-5 py-3.5"><x-badge variant="info">{{ $order->platform->name }}</x-badge></td>
                    <td class="px-5 py-3.5 text-gray-600">{{ $order->customer_name ?? '—' }}</td>
                    <td class="px-5 py-3.5 text-right font-medium text-gray-900">₹{{ number_format($order->gross_revenue) }}</td>
                    <td class="px-5 py-3.5 text-center">
                        @php
                            $shipmentVariant = match($order->status) {
                                'delivered' => 'success',
                                'shipped', 'in_transit' => 'warning',
                                default => 'default'
                            };
                        @endphp
                        <x-badge :variant="$shipmentVariant">{{ ucfirst($order->status) }}</x-badge>
                    </td>
                    <td class="px-5 py-3.5 text-right text-gray-400 text-xs">{{ $order->created_at->format('d M') }}</td>
                    <td class="px-5 py-3.5 text-right">
                        <a href="{{ route('orders.show', $order) }}" class="touch-target flex items-center justify-center p-1.5 rounded-lg text-gray-400 hover:text-brand-600 hover:bg-brand-50 transition-all"><i class="bi bi-eye text-sm"></i></a>
                    </td>
                </tr>
                @empty
                <tr><td colspan="8"><x-empty-state icon="receipt" title="No orders yet" description="Record your first sale to start tracking." /></td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{-- ═══════ MOBILE CARDS ═══════ --}}
    <div class="md:hidden divide-y divide-gray-100">
        @forelse($orders as $order)
        <a href="{{ route('orders.show', $order) }}" class="block p-4 hover:bg-gray-50 transition-colors">
            <div class="flex items-start justify-between gap-3">
                <div class="min-w-0 flex-1">
                    <div class="flex items-center gap-2 mb-1">
                        <span class="font-bold text-gray-900 text-sm">{{ $order->order_number ?? '#' . $order->id }}</span>
                        <x-badge variant="info" class="text-[10px]">{{ $order->platform->name }}</x-badge>
                    </div>
                    @foreach($order->items->take(2) as $item)
                        <p class="text-xs text-gray-600 truncate">{{ $item->product->name }} × {{ $item->quantity }}</p>
                    @endforeach
                    @if($order->items->count() > 2)
                        <p class="text-[10px] text-gray-400">+{{ $order->items->count() - 2 }} more items</p>
                    @endif
                </div>
                <div class="text-right shrink-0">
                    <p class="font-bold text-gray-900 text-sm">₹{{ number_format($order->gross_revenue) }}</p>
                    <p class="text-[10px] text-gray-400 mt-0.5">{{ $order->created_at->format('d M') }}</p>
                </div>
            </div>
            <div class="flex items-center gap-2 mt-2">
                @php
                    $shipmentVariant = match($order->status) {
                        'delivered' => 'success',
                        'shipped', 'in_transit' => 'warning',
                        default => 'default'
                    };
                @endphp
                <x-badge :variant="$shipmentVariant">{{ ucfirst($order->status) }}</x-badge>
                @foreach($order->items as $item)
                    @php
                        $itemVariant = match($item->status) {
                            'successful' => 'success',
                            'customer_return', 'rto' => 'warning',
                            'missing' => 'danger',
                            default => 'default'
                        };
                    @endphp
                    <x-badge :variant="$itemVariant" class="text-[10px]">{{ ucfirst(str_replace('_', ' ', $item->status)) }}</x-badge>
                @endforeach
            </div>
        </a>
        @empty
            <div class="p-6">
                <x-empty-state icon="receipt" title="No orders yet" description="Record your first sale to start tracking." />
            </div>
        @endforelse
    </div>

    @if($orders->hasPages())
        <div class="border-t border-gray-100 px-5 py-3">
            {{ $orders->withQueryString()->links() }}
        </div>
    @endif
</x-card>
@endsection
