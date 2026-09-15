@extends('layouts.app')
@section('title', 'Import Results')
@section('subtitle', $fileName)

@section('content')
@php
    $problems = count($result['errors']) + count($result['skipped']);
    $shown = 50;
@endphp
<div class="max-w-3xl space-y-6">

    <div class="flex flex-wrap items-center justify-between gap-2">
        <div>
            <h2 class="text-lg font-bold text-gray-900">
                {{ $result['dry_run'] ? 'Check complete — nothing was saved' : 'Import complete' }}
            </h2>
            <p class="text-sm text-gray-500 truncate max-w-md">{{ $fileName }}</p>
        </div>
        <div class="flex gap-2">
            <a href="{{ route('orders.import.create') }}" class="touch-target inline-flex items-center gap-1.5 rounded-xl border border-gray-300 bg-white px-3.5 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50 transition-all">
                <i class="bi bi-upload"></i> Import another
            </a>
            <a href="{{ route('orders.index') }}" class="touch-target inline-flex items-center gap-1.5 rounded-xl bg-brand-600 px-3.5 py-2 text-sm font-semibold text-white shadow-lg shadow-brand-200 hover:bg-brand-700 transition-all">
                <i class="bi bi-list-ul"></i> View orders
            </a>
        </div>
    </div>

    @if($result['dry_run'])
        <div class="rounded-xl border border-amber-200 bg-amber-50 p-4 flex gap-3">
            <i class="bi bi-info-circle text-amber-600 text-lg"></i>
            <p class="text-sm text-amber-800">This was a check run. Re-upload the same file with “Check only” switched off to actually save these orders.</p>
        </div>
    @endif

    <div class="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-5">
        @foreach([
            ['Rows read', $result['total_rows'], 'text-gray-900'],
            [$result['dry_run'] ? 'Orders ready' : 'Orders created', $result['orders_created'], 'text-emerald-600'],
            ['Items added', $result['items_created'], 'text-emerald-600'],
            ['Status updated', $result['orders_updated'], 'text-brand-600'],
            ['Already present', $result['duplicates'], 'text-gray-500'],
        ] as [$label, $value, $colour])
            <div class="rounded-xl border border-gray-200 bg-white p-3.5">
                <p class="text-[10px] font-bold uppercase tracking-wider text-gray-400 leading-tight">{{ $label }}</p>
                <p class="mt-1 text-xl font-bold {{ $colour }}">{{ number_format($value) }}</p>
            </div>
        @endforeach
    </div>

    @if($result['auto_charged'])
        <div class="rounded-xl border border-blue-200 bg-blue-50 p-4 flex gap-3">
            <i class="bi bi-percent text-blue-600 text-lg"></i>
            <p class="text-sm text-blue-800">The file carried no fee columns, so commission, shipping, closing and GST were filled in from each platform's charge structure.</p>
        </div>
    @endif

    @if($result['errors'])
        <x-card title="Rows that could not be imported" :subtitle="count($result['errors']) . ' row(s)'">
            <div class="divide-y divide-gray-100">
                @foreach(array_slice($result['errors'], 0, $shown) as $error)
                    <div class="flex gap-3 py-2.5 text-sm">
                        <span class="shrink-0 w-14 font-mono text-xs text-gray-400">line {{ $error['line'] }}</span>
                        <span class="text-rose-700">{{ $error['message'] }}</span>
                    </div>
                @endforeach
            </div>
            @if(count($result['errors']) > $shown)
                <p class="pt-3 text-xs text-gray-400">…and {{ count($result['errors']) - $shown }} more.</p>
            @endif
        </x-card>
    @endif

    @if($result['skipped'])
        <x-card title="Rows deliberately skipped" :subtitle="count($result['skipped']) . ' row(s)'">
            <div class="divide-y divide-gray-100">
                @foreach(array_slice($result['skipped'], 0, $shown) as $skip)
                    <div class="flex gap-3 py-2.5 text-sm">
                        <span class="shrink-0 w-14 font-mono text-xs text-gray-400">line {{ $skip['line'] }}</span>
                        <span class="text-gray-600">{{ $skip['message'] }}</span>
                    </div>
                @endforeach
            </div>
            @if(count($result['skipped']) > $shown)
                <p class="pt-3 text-xs text-gray-400">…and {{ count($result['skipped']) - $shown }} more.</p>
            @endif
        </x-card>
    @endif

    @if($problems === 0 && $result['total_rows'] === 0)
        <div class="rounded-xl border border-gray-200 bg-gray-50 p-4 text-sm text-gray-600">
            The file had no data rows below the header. Check that you exported the orders themselves and not a summary sheet.
        </div>
    @elseif($problems === 0)
        <div class="rounded-xl border border-emerald-200 bg-emerald-50 p-4 flex gap-3">
            <i class="bi bi-check-circle text-emerald-600 text-lg"></i>
            <p class="text-sm text-emerald-800">Every row in the file was understood.</p>
        </div>
    @endif
</div>
@endsection
