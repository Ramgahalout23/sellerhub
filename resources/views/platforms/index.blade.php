@extends('layouts.app')
@section('title', 'Platforms')
@section('subtitle', $platforms->count() . ' platforms configured')

@section('actions')
<a href="{{ route('platforms.create') }}" class="touch-target inline-flex items-center gap-1.5 rounded-xl bg-brand-600 px-3 py-2 text-sm font-semibold text-white shadow-lg shadow-brand-200 hover:bg-brand-700 transition-all">
    <i class="bi bi-plus-lg"></i> <span class="hidden sm:inline">Add Platform</span><span class="sm:hidden">Add</span>
</a>
@endsection

@section('content')
<x-card padding="false">
    {{-- ═══════ DESKTOP TABLE ═══════ --}}
    <div class="hidden md:block overflow-x-auto">
        <table class="w-full text-sm">
            <thead>
                <tr class="border-b border-gray-100">
                    <th class="px-5 py-3 text-left text-[10px] font-bold uppercase tracking-wider text-gray-400">Platform</th>
                    <th class="px-5 py-3 text-center text-[10px] font-bold uppercase tracking-wider text-gray-400">Commission</th>
                    <th class="px-5 py-3 text-center text-[10px] font-bold uppercase tracking-wider text-gray-400">Shipping</th>
                    <th class="px-5 py-3 text-center text-[10px] font-bold uppercase tracking-wider text-gray-400">Closing</th>
                    <th class="px-5 py-3 text-center text-[10px] font-bold uppercase tracking-wider text-gray-400">GST</th>
                    <th class="px-5 py-3 text-center text-[10px] font-bold uppercase tracking-wider text-gray-400">Status</th>
                    <th class="px-5 py-3 text-right text-[10px] font-bold uppercase tracking-wider text-gray-400">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-50">
                @forelse($platforms as $platform)
                <tr class="hover:bg-gray-50/50 transition-colors">
                    <td class="px-5 py-4">
                        <a href="{{ route('platforms.show', $platform) }}" class="font-semibold text-gray-900 hover:text-brand-600 transition-colors">{{ $platform->name }}</a>
                        <p class="text-xs text-gray-400 mt-0.5">{{ $platform->slug }}</p>
                    </td>
                    <td class="px-5 py-4 text-center font-medium text-gray-700">{{ $platform->charge_structure['commission_percent'] ?? 0 }}%</td>
                    <td class="px-5 py-4 text-center text-gray-600">₹{{ number_format($platform->charge_structure['shipping_fee'] ?? 0) }}</td>
                    <td class="px-5 py-4 text-center text-gray-600">₹{{ number_format($platform->charge_structure['closing_fee'] ?? 0) }}</td>
                    <td class="px-5 py-4 text-center text-gray-600">{{ $platform->charge_structure['gst_percent'] ?? 0 }}%</td>
                    <td class="px-5 py-4 text-center">
                        @if($platform->is_active)
                            <x-badge variant="success">Active</x-badge>
                        @else
                            <x-badge variant="default">Inactive</x-badge>
                        @endif
                    </td>
                    <td class="px-5 py-4 text-right">
                        <div class="flex items-center justify-end gap-1">
                            <a href="{{ route('platforms.show', $platform) }}" class="touch-target flex items-center justify-center rounded-lg text-gray-400 hover:text-brand-600 hover:bg-brand-50 transition-all" aria-label="View platform"><i class="bi bi-eye text-base"></i></a>
                            <form action="{{ route('platforms.destroy', $platform) }}" method="POST" class="inline" onsubmit="return confirm('Delete this platform?')">
                                @csrf @method('DELETE')
                                <button class="touch-target flex items-center justify-center rounded-lg text-gray-400 hover:text-rose-600 hover:bg-rose-50 transition-all" aria-label="Delete platform"><i class="bi bi-trash text-base"></i></button>
                            </form>
                        </div>
                    </td>
                </tr>
                @empty
                <tr><td colspan="7"><x-empty-state icon="globe" title="No platforms yet" description="Add your first platform to start tracking charges." /></td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{-- ═══════ MOBILE CARDS ═══════ --}}
    <div class="md:hidden divide-y divide-gray-100">
        @forelse($platforms as $platform)
        <a href="{{ route('platforms.show', $platform) }}" class="block p-4 hover:bg-gray-50 transition-colors">
            <div class="flex items-center justify-between gap-3 mb-2">
                <div class="min-w-0">
                    <p class="font-bold text-gray-900 text-sm truncate">{{ $platform->name }}</p>
                    <p class="text-[11px] text-gray-400">{{ $platform->slug }}</p>
                </div>
                <div class="shrink-0">
                @if($platform->is_active)
                    <x-badge variant="success">Active</x-badge>
                @else
                    <x-badge variant="default">Inactive</x-badge>
                @endif
                </div>
            </div>
            <div class="grid grid-cols-2 gap-2 min-[380px]:grid-cols-4">
                <div><p class="text-[10px] text-gray-400">Commission</p><p class="text-xs font-bold text-gray-900">{{ $platform->charge_structure['commission_percent'] ?? 0 }}%</p></div>
                <div><p class="text-[10px] text-gray-400">Shipping</p><p class="text-xs font-bold text-gray-900">₹{{ number_format($platform->charge_structure['shipping_fee'] ?? 0) }}</p></div>
                <div><p class="text-[10px] text-gray-400">Closing</p><p class="text-xs font-bold text-gray-900">₹{{ number_format($platform->charge_structure['closing_fee'] ?? 0) }}</p></div>
                <div><p class="text-[10px] text-gray-400">GST</p><p class="text-xs font-bold text-gray-900">{{ $platform->charge_structure['gst_percent'] ?? 0 }}%</p></div>
            </div>
        </a>
        @empty
            <div class="p-6">
                <x-empty-state icon="globe" title="No platforms yet" description="Add your first platform to start tracking charges." />
            </div>
        @endforelse
    </div>
</x-card>
@endsection
