@extends('layouts.app')
@section('title', 'Import Orders')
@section('subtitle', 'Upload a marketplace order report')

@section('content')
@php
    $required = ['order_number', 'sku', 'quantity', 'selling_price'];
@endphp
<div class="max-w-3xl space-y-6">

    <div class="flex flex-wrap items-center justify-between gap-2">
        <div>
            <h2 class="text-lg font-bold text-gray-900">Import orders from a CSV</h2>
            <p class="text-sm text-gray-500">Paste in an Amazon / Flipkart / Meesho report instead of typing each order</p>
        </div>
        <a href="{{ route('orders.index') }}" class="text-sm font-medium text-gray-500 hover:text-gray-700 transition-colors">
            <i class="bi bi-arrow-left mr-1"></i> Back to Orders
        </a>
    </div>

    <form action="{{ route('orders.import.store') }}" method="POST" enctype="multipart/form-data">
        @csrf
        <x-card title="Report file" subtitle="One row per order line, with a header row">
            <div class="space-y-5">

                <div>
                    <label class="block text-xs font-bold uppercase tracking-wider text-gray-500 mb-2">CSV file *</label>
                    <input type="file" name="file" accept=".csv,.txt,text/csv" required
                           class="w-full rounded-xl border border-gray-200 bg-gray-50 px-4 py-2.5 text-sm file:mr-3 file:rounded-lg file:border-0 file:bg-brand-50 file:px-3 file:py-1.5 file:text-xs file:font-semibold file:text-brand-700 focus:border-brand-500 focus:ring-2 focus:ring-brand-500/20 focus:outline-none transition-all">
                    @error('file')<p class="mt-1 text-xs text-rose-600">{{ $message }}</p>@enderror
                    <p class="mt-1.5 text-[11px] text-gray-400">
                        Up to 4 MB / 2,000 rows. Export as <span class="font-medium text-gray-500">CSV UTF-8</span> — Excel's other formats are not readable.
                        <a href="{{ route('orders.import.template') }}" class="font-semibold text-brand-600 hover:text-brand-700">Download a template</a>.
                    </p>
                </div>

                <div class="grid grid-cols-1 gap-5 sm:grid-cols-2">
                    <div>
                        <label class="block text-xs font-bold uppercase tracking-wider text-gray-500 mb-2">Platform *</label>
                        <select name="platform_id" class="w-full rounded-xl border border-gray-200 bg-gray-50 px-4 py-2.5 text-sm focus:border-brand-500 focus:ring-2 focus:ring-brand-500/20 focus:outline-none transition-all">
                            <option value="">Use the platform column in the file</option>
                            @foreach($platforms as $p)
                                <option value="{{ $p->id }}" {{ old('platform_id') == $p->id ? 'selected' : '' }}>{{ $p->name }}</option>
                            @endforeach
                        </select>
                        <p class="mt-1.5 text-[11px] text-gray-400">Used for rows whose platform value is blank.</p>
                    </div>

                    <div class="flex items-end">
                        <label class="flex items-start gap-3 rounded-xl border border-gray-200 bg-gray-50/60 p-3.5 cursor-pointer w-full">
                            <input type="checkbox" name="dry_run" value="1" class="mt-0.5 w-4 h-4 rounded text-brand-600 focus:ring-brand-500" {{ old('dry_run') ? 'checked' : '' }}>
                            <span>
                                <span class="block text-sm font-semibold text-gray-900">Check only, don't save</span>
                                <span class="block text-[11px] text-gray-500">See exactly what would happen — nothing is written and stock is untouched.</span>
                            </span>
                        </label>
                    </div>
                </div>

                <div class="pt-4 border-t border-gray-100 flex items-center justify-end gap-2">
                    <a href="{{ route('orders.index') }}" class="touch-target inline-flex items-center justify-center rounded-xl border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50 transition-all">Cancel</a>
                    <button type="submit" class="touch-target inline-flex items-center justify-center gap-1.5 rounded-xl bg-brand-600 px-5 py-2 text-sm font-semibold text-white shadow-lg shadow-brand-200 hover:bg-brand-700 transition-all">
                        <i class="bi bi-upload"></i> Import orders
                    </button>
                </div>
            </div>
        </x-card>
    </form>

    <x-card title="What the importer looks for" subtitle="Column names are matched loosely — order does not matter">
        <div class="space-y-4 text-sm">
            <p class="text-gray-600">
                Headers are matched case-insensitively and ignore spaces, dashes and underscores, so
                <span class="font-mono text-xs bg-gray-100 px-1.5 py-0.5 rounded">Order ID</span>,
                <span class="font-mono text-xs bg-gray-100 px-1.5 py-0.5 rounded">order_id</span> and
                <span class="font-mono text-xs bg-gray-100 px-1.5 py-0.5 rounded">Order-Id</span> all work.
                Rows that share an order number become <span class="font-semibold text-gray-900">one order with several items</span>.
            </p>

            <details class="rounded-xl border border-gray-200 bg-gray-50/50 open:bg-white transition-colors">
                <summary class="cursor-pointer px-4 py-3 text-xs font-bold uppercase tracking-wider text-gray-500">Accepted column names</summary>
                <div class="px-4 pb-4 overflow-x-auto">
                    <table class="w-full text-xs">
                        <thead>
                            <tr class="text-left text-[10px] font-bold uppercase tracking-wider text-gray-400">
                                <th class="py-2 pr-4">Column</th>
                                <th class="py-2">Also accepts</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            @foreach($aliases as $canonical => $names)
                                <tr>
                                    <td class="py-2 pr-4 align-top whitespace-nowrap">
                                        <span class="font-mono font-semibold text-gray-700">{{ $canonical }}</span>
                                        @if(in_array($canonical, $required, true))
                                            <span class="ml-1 text-[10px] font-bold text-rose-500">required</span>
                                        @elseif(in_array($canonical, $chargeColumns, true))
                                            <span class="ml-1 text-[10px] font-bold text-amber-500">fees</span>
                                        @endif
                                    </td>
                                    <td class="py-2 text-gray-500">
                                        {{ implode(', ', array_slice($names, 0, 4)) }}@if(count($names) > 4), …@endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </details>

            <ul class="space-y-2 text-xs text-gray-500">
                <li class="flex gap-2"><i class="bi bi-percent text-gray-400"></i><span><span class="font-semibold text-gray-700">No fee columns?</span> Charges are filled automatically from each platform's charge structure, so profit is right from day one. If the file does list fees, the file wins.</span></li>
                <li class="flex gap-2"><i class="bi bi-arrow-repeat text-gray-400"></i><span><span class="font-semibold text-gray-700">Already imported?</span> Re-importing the same report never duplicates an order — it only refreshes its shipment status (handy for a weekly "what got delivered" export).</span></li>
                <li class="flex gap-2"><i class="bi bi-box-seam text-gray-400"></i><span><span class="font-semibold text-gray-700">Stock is deducted FIFO</span> exactly like the manual order form, so batch costs stay correct.</span></li>
                <li class="flex gap-2"><i class="bi bi-x-octagon text-gray-400"></i><span>Cancelled rows are skipped and reported, since a cancelled sale should not consume stock.</span></li>
            </ul>
        </div>
    </x-card>
</div>
@endsection
