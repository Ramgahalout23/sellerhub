@extends('layouts.app')
@section('title', 'Batch Orders')
@section('subtitle', $batchOrders->total() . ' batch orders')

@section('actions')
<a href="{{ route('batch-orders.create') }}" class="touch-target inline-flex items-center gap-1.5 rounded-xl bg-brand-600 px-3 py-2 text-sm font-semibold text-white shadow-lg shadow-brand-200 hover:bg-brand-700 transition-all">
    <i class="bi bi-plus-lg"></i> <span class="hidden sm:inline">New Batch Order</span><span class="sm:hidden">New</span>
</a>
@endsection

@section('content')
<x-card padding="false">
    <x-slot:action>
        <form class="flex gap-2" method="GET">
            <select name="supplier_id" class="rounded-lg border border-gray-200 bg-gray-50 px-3 py-2 text-sm focus:border-brand-500 focus:ring-1 focus:ring-brand-500 focus:outline-none">
                <option value="">All Suppliers</option>
                @foreach($suppliers as $s)
                    <option value="{{ $s->id }}" {{ request('supplier_id') == $s->id ? 'selected' : '' }}>{{ $s->name }}</option>
                @endforeach
            </select>
            <button class="touch-target rounded-lg bg-brand-600 px-3 py-2 text-sm font-medium text-white hover:bg-brand-700 transition-colors"><i class="bi bi-funnel"></i></button>
        </form>
    </x-slot:action>

    {{-- ═══════ DESKTOP TABLE ═══════ --}}
    <div class="hidden md:block overflow-x-auto">
        <table class="w-full text-sm">
            <thead>
                <tr class="border-b border-gray-100">
                    <th class="px-5 py-3 text-left text-[10px] font-bold uppercase tracking-wider text-gray-400">Date</th>
                    <th class="px-5 py-3 text-left text-[10px] font-bold uppercase tracking-wider text-gray-400">Supplier</th>
                    <th class="px-5 py-3 text-center text-[10px] font-bold uppercase tracking-wider text-gray-400">Items</th>
                    <th class="px-5 py-3 text-right text-[10px] font-bold uppercase tracking-wider text-gray-400">Total Cost</th>
                    <th class="px-5 py-3 text-left text-[10px] font-bold uppercase tracking-wider text-gray-400">Notes</th>
                    <th class="px-5 py-3 text-right text-[10px] font-bold uppercase tracking-wider text-gray-400"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-50">
                @forelse($batchOrders as $batch)
                <tr class="hover:bg-gray-50/50 transition-colors">
                    <td class="px-5 py-3.5 text-gray-600">{{ $batch->order_date->format('d M Y') }}</td>
                    <td class="px-5 py-3.5">
                        <a href="{{ route('suppliers.show', $batch->supplier) }}" class="font-semibold text-gray-900 hover:text-brand-600 transition-colors">{{ $batch->supplier->name }}</a>
                    </td>
                    <td class="px-5 py-3.5 text-center"><x-badge variant="default">{{ $batch->items_count ?? $batch->items->count() }} items</x-badge></td>
                    <td class="px-5 py-3.5 text-right font-bold text-gray-900">₹{{ number_format($batch->total_cost) }}</td>
                    <td class="px-5 py-3.5 text-gray-500 text-xs">{{ Str::limit($batch->notes, 30) }}</td>
                    <td class="px-5 py-3.5 text-right">
                        <div class="flex items-center justify-end gap-1">
                            <a href="{{ route('batch-orders.show', $batch) }}" class="p-1.5 rounded-lg text-gray-400 hover:text-brand-600 hover:bg-brand-50 transition-all"><i class="bi bi-eye text-sm"></i></a>
                            <form action="{{ route('batch-orders.destroy', $batch) }}" method="POST" class="inline" onsubmit="return confirm('Delete this batch? Stock will be reversed.')">
                                @csrf @method('DELETE')
                                <button class="p-1.5 rounded-lg text-gray-400 hover:text-rose-600 hover:bg-rose-50 transition-all"><i class="bi bi-trash text-sm"></i></button>
                            </form>
                        </div>
                    </td>
                </tr>
                @empty
                <tr><td colspan="6"><x-empty-state icon="cart-plus" title="No batch orders yet" description="Record your first purchase batch." /></td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{-- ═══════ MOBILE CARDS ═══════ --}}
    <div class="md:hidden divide-y divide-gray-100">
        @forelse($batchOrders as $batch)
        <a href="{{ route('batch-orders.show', $batch) }}" class="block p-4 hover:bg-gray-50 transition-colors">
            <div class="flex items-start justify-between gap-3">
                <div class="min-w-0 flex-1">
                    <div class="flex items-center gap-2 flex-wrap">
                        <span class="font-bold text-gray-900 text-sm">Batch #{{ $batch->id }}</span>
                        <x-badge variant="info" class="text-[10px]">{{ $batch->items_count ?? $batch->items->count() }} items</x-badge>
                    </div>
                    <p class="text-xs text-gray-500 mt-0.5">{{ $batch->order_date->format('d M Y') }}</p>
                    <p class="text-xs text-gray-600 font-medium mt-0.5">{{ $batch->supplier->name }}</p>
                    @if($batch->notes)
                        <p class="text-[11px] text-gray-400 mt-0.5">{{ Str::limit($batch->notes, 50) }}</p>
                    @endif
                </div>
                <div class="text-right shrink-0">
                    <p class="text-lg font-bold text-gray-900">₹{{ number_format($batch->total_cost) }}</p>
                </div>
            </div>
        </a>
        @empty
            <div class="p-6">
                <x-empty-state icon="cart-plus" title="No batch orders yet" description="Record your first purchase batch." />
            </div>
        @endforelse
    </div>

    @if($batchOrders->hasPages())
        <div class="border-t border-gray-100 px-5 py-3">
            {{ $batchOrders->withQueryString()->links() }}
        </div>
    @endif
</x-card>
@endsection
