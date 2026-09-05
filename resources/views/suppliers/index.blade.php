@extends('layouts.app')
@section('title', 'Suppliers')
@section('subtitle', $suppliers->total() . ' suppliers')

@section('actions')
<a href="{{ route('suppliers.create') }}" class="touch-target inline-flex items-center gap-1.5 rounded-xl bg-brand-600 px-3 py-2 text-sm font-semibold text-white shadow-lg shadow-brand-200 hover:bg-brand-700 transition-all">
    <i class="bi bi-plus-lg"></i> <span class="hidden sm:inline">Add Supplier</span><span class="sm:hidden">Add</span>
</a>
@endsection

@section('content')
<x-card padding="false">
    <x-slot:action>
        <form class="flex gap-2" method="GET">
            <input type="text" name="search" class="rounded-lg border border-gray-200 bg-gray-50 px-3 py-2 text-sm placeholder:text-gray-400 focus:border-brand-500 focus:ring-1 focus:ring-brand-500 focus:outline-none flex-1 min-w-[120px]" placeholder="Search suppliers..." value="{{ request('search') }}">
            <button class="touch-target rounded-lg bg-brand-600 px-3 py-2 text-sm font-medium text-white hover:bg-brand-700 transition-colors"><i class="bi bi-search"></i></button>
        </form>
    </x-slot:action>

    {{-- ═══════ DESKTOP TABLE ═══════ --}}
    <div class="hidden md:block overflow-x-auto">
        <table class="w-full text-sm">
            <thead>
                <tr class="border-b border-gray-100">
                    <th class="px-5 py-3 text-left text-[10px] font-bold uppercase tracking-wider text-gray-400">Supplier</th>
                    <th class="px-5 py-3 text-left text-[10px] font-bold uppercase tracking-wider text-gray-400">Contact</th>
                    <th class="px-5 py-3 text-left text-[10px] font-bold uppercase tracking-wider text-gray-400">Phone</th>
                    <th class="px-5 py-3 text-center text-[10px] font-bold uppercase tracking-wider text-gray-400">Products</th>
                    <th class="px-5 py-3 text-center text-[10px] font-bold uppercase tracking-wider text-gray-400">Status</th>
                    <th class="px-5 py-3 text-right text-[10px] font-bold uppercase tracking-wider text-gray-400">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-50">
                @forelse($suppliers as $supplier)
                <tr class="hover:bg-gray-50/50 transition-colors">
                    <td class="px-5 py-3.5">
                        <div>
                            <a href="{{ route('suppliers.show', $supplier) }}" class="font-semibold text-gray-900 hover:text-brand-600 transition-colors">{{ $supplier->name }}</a>
                            <p class="text-xs text-gray-400 mt-0.5">{{ Str::limit($supplier->address, 35) }}</p>
                        </div>
                    </td>
                    <td class="px-5 py-3.5 text-gray-600">{{ $supplier->contact_person ?? '—' }}</td>
                    <td class="px-5 py-3.5 text-gray-600">{{ $supplier->phone ?? '—' }}</td>
                    <td class="px-5 py-3.5 text-center"><x-badge variant="default">{{ $supplier->products_count ?? $supplier->products->count() ?? 0 }}</x-badge></td>
                    <td class="px-5 py-3.5 text-center">
                        @if($supplier->is_active)
                            <x-badge variant="success">Active</x-badge>
                        @else
                            <x-badge variant="default">Inactive</x-badge>
                        @endif
                    </td>
                    <td class="px-5 py-3.5 text-right">
                        <div class="flex items-center justify-end gap-1">
                            <a href="{{ route('suppliers.show', $supplier) }}" class="p-1.5 rounded-lg text-gray-400 hover:text-brand-600 hover:bg-brand-50 transition-all"><i class="bi bi-eye text-sm"></i></a>
                            <a href="{{ route('suppliers.edit', $supplier) }}" class="p-1.5 rounded-lg text-gray-400 hover:text-gray-600 hover:bg-gray-100 transition-all"><i class="bi bi-pencil text-sm"></i></a>
                        </div>
                    </td>
                </tr>
                @empty
                <tr><td colspan="6"><x-empty-state icon="truck" title="No suppliers yet" description="Add your first supplier to start tracking purchases." /></td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{-- ═══════ MOBILE CARDS ═══════ --}}
    <div class="md:hidden divide-y divide-gray-100">
        @forelse($suppliers as $supplier)
        <a href="{{ route('suppliers.show', $supplier) }}" class="block p-4 hover:bg-gray-50 transition-colors">
            <div class="flex items-start justify-between gap-3">
                <div class="min-w-0 flex-1">
                    <div class="flex items-center gap-2">
                        <p class="font-bold text-gray-900 text-sm">{{ $supplier->name }}</p>
                        @if($supplier->is_active)
                            <x-badge variant="success" class="text-[10px]">Active</x-badge>
                        @else
                            <x-badge variant="default" class="text-[10px]">Inactive</x-badge>
                        @endif
                    </div>
                    <p class="text-xs text-gray-500 mt-0.5">{{ $supplier->contact_person ?? '' }} {{ $supplier->phone ? '• ' . $supplier->phone : '' }}</p>
                    <p class="text-[11px] text-gray-400 mt-0.5 truncate">{{ $supplier->address }}</p>
                </div>
                <div class="text-right shrink-0">
                    <p class="text-lg font-bold text-gray-900">{{ $supplier->products_count ?? $supplier->products->count() ?? 0 }}</p>
                    <p class="text-[10px] text-gray-400">products</p>
                </div>
            </div>
        </a>
        @empty
            <div class="p-6">
                <x-empty-state icon="truck" title="No suppliers yet" description="Add your first supplier to start tracking purchases." />
            </div>
        @endforelse
    </div>

    @if($suppliers->hasPages())
        <div class="border-t border-gray-100 px-5 py-3">
            {{ $suppliers->withQueryString()->links() }}
        </div>
    @endif
</x-card>
@endsection
