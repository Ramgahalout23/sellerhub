@extends('layouts.app')
@section('title', 'Alerts & Notifications')
@section('subtitle', $unreadCount . ' unread')

@section('actions')
@if($unreadCount > 0)
    <form action="{{ route('alerts.read-all') }}" method="POST" class="inline">
        @csrf @method('PATCH')
        <button class="touch-target inline-flex items-center gap-1.5 rounded-xl border border-gray-300 bg-white px-3 py-2 text-sm font-medium text-gray-700 shadow-sm hover:bg-gray-50 transition-all">
            <i class="bi bi-check-all text-brand-500"></i> <span class="hidden sm:inline">Mark All Read</span><span class="sm:hidden">Read All</span>
        </button>
    </form>
@endif
@endsection

@section('content')
<x-card padding="false">
    <x-slot:action>
        <form class="flex gap-2" method="GET">
            <select name="type" class="rounded-lg border border-gray-200 bg-gray-50 px-3 py-1.5 text-sm focus:border-brand-500 focus:ring-1 focus:ring-brand-500 focus:outline-none">
                <option value="">All Types</option>
                <option value="return_reminder" {{ request('type') === 'return_reminder' ? 'selected' : '' }}>Return Reminders</option>
                <option value="low_stock" {{ request('type') === 'low_stock' ? 'selected' : '' }}>Low Stock</option>
            </select>
            <button class="rounded-lg bg-brand-600 px-3 py-1.5 text-sm font-medium text-white hover:bg-brand-700 transition-colors"><i class="bi bi-funnel"></i></button>
        </form>
    </x-slot:action>

    @forelse($alerts as $alert)
        <div class="flex items-start gap-4 px-5 py-4 border-b border-gray-50 hover:bg-gray-50/50 transition-colors {{ $alert->is_read ? 'opacity-60' : '' }}">
            <div class="mt-0.5 flex-shrink-0">
                @if(!$alert->is_read)
                    <span class="block h-2.5 w-2.5 rounded-full bg-brand-500"></span>
                @else
                    <span class="block h-2.5 w-2.5 rounded-full bg-gray-300"></span>
                @endif
            </div>
            <div class="flex-1 min-w-0">
                <div class="flex items-start justify-between gap-3">
                    <h4 class="text-sm font-semibold text-gray-900 {{ $alert->is_read ? 'text-gray-500 font-medium' : '' }}">
                        @if($alert->type === 'return_reminder')
                            <i class="bi bi-bell text-sky-500 mr-1"></i>
                        @elseif($alert->type === 'low_stock')
                            <i class="bi bi-exclamation-triangle text-amber-500 mr-1"></i>
                        @else
                            <i class="bi bi-info-circle text-gray-400 mr-1"></i>
                        @endif
                        {{ $alert->title }}
                    </h4>
                    <span class="text-[11px] text-gray-400 whitespace-nowrap flex-shrink-0">{{ $alert->created_at->diffForHumans() }}</span>
                </div>
                <p class="text-xs text-gray-500 mt-1 leading-relaxed">{{ $alert->message }}</p>
            </div>
            @if(!$alert->is_read)
            <form action="{{ route('alerts.read', $alert) }}" method="POST" class="flex-shrink-0">
                @csrf @method('PATCH')
                <button class="touch-target flex items-center justify-center p-1 rounded-lg text-gray-400 hover:text-brand-600 hover:bg-brand-50 transition-all" title="Mark read"><i class="bi bi-check-lg"></i></button>
            </form>
            @endif
        </div>
    @empty
        <x-empty-state icon="bell-slash" title="All caught up!" description="No alerts right now." />
    @endforelse

    @if($alerts->hasPages())
        <div class="border-t border-gray-100 px-5 py-3">
            {{ $alerts->withQueryString()->links() }}
        </div>
    @endif
</x-card>
@endsection
