@extends('layouts.app')
@section('title', 'Batch Order #' . $batchOrder->id)

@section('actions')
<a href="{{ route('batch-orders.edit', $batchOrder) }}" class="touch-target inline-flex items-center gap-1.5 rounded-xl border border-gray-300 bg-white px-3 py-2 text-sm font-medium text-gray-700 shadow-sm hover:bg-gray-50 transition-all">
    <i class="bi bi-pencil text-xs"></i> <span class="hidden sm:inline">Edit</span>
</a>
@endsection

@section('content')
<div class="grid grid-cols-1 gap-6 lg:grid-cols-4">
    <div class="lg:col-span-1">
        <x-card title="Batch Order Details">
            <div class="space-y-3">
                <div><p class="text-[10px] font-bold uppercase tracking-wider text-gray-400 mb-0.5">Supplier</p><a href="{{ route('suppliers.show', $batchOrder->supplier) }}" class="text-sm font-medium text-brand-600 hover:text-brand-700">{{ $batchOrder->supplier->name }}</a></div>
                <div><p class="text-[10px] font-bold uppercase tracking-wider text-gray-400 mb-0.5">Order Date</p><p class="text-sm text-gray-700">{{ $batchOrder->order_date->format('d M Y') }}</p></div>
                <div><p class="text-[10px] font-bold uppercase tracking-wider text-gray-400 mb-0.5">Total Cost</p><p class="text-2xl font-bold text-gray-900">₹{{ number_format($batchOrder->total_cost) }}</p></div>
                <div><p class="text-[10px] font-bold uppercase tracking-wider text-gray-400 mb-0.5">Notes</p><p class="text-sm text-gray-700">{{ $batchOrder->notes ?? '—' }}</p></div>
            </div>
        </x-card>
    </div>
    <div class="lg:col-span-3">
        <x-card title="Items Purchased">
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b border-gray-100">
                            <th class="pb-2.5 text-left text-[10px] font-bold uppercase tracking-wider text-gray-400">Product</th>
                            <th class="pb-2.5 text-left text-[10px] font-bold uppercase tracking-wider text-gray-400">SKU</th>
                            <th class="pb-2.5 text-right text-[10px] font-bold uppercase tracking-wider text-gray-400">Qty</th>
                            <th class="pb-2.5 text-right text-[10px] font-bold uppercase tracking-wider text-gray-400">Unit Cost</th>
                            <th class="pb-2.5 text-right text-[10px] font-bold uppercase tracking-wider text-gray-400">Total</th>
                            <th class="pb-2.5 text-right text-[10px] font-bold uppercase tracking-wider text-gray-400"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-50">
                        @foreach($batchOrder->items as $item)
                        <tr class="hover:bg-gray-50/50 transition-colors" id="item-{{ $item->id }}">
                            <td class="py-2.5"><a href="{{ route('products.show', $item->product) }}" class="font-medium text-brand-600 hover:text-brand-700">{{ $item->product->name }}</a></td>
                            <td class="py-2.5"><code class="text-xs bg-gray-100 px-1.5 py-0.5 rounded-md text-gray-600">{{ $item->product->sku }}</code></td>
                            <td class="py-2.5 text-right text-gray-600">{{ $item->quantity }}</td>
                            <td class="py-2.5 text-right text-gray-600">₹{{ number_format($item->unit_cost) }}</td>
                            <td class="py-2.5 text-right font-bold text-gray-900">₹{{ number_format($item->total_cost) }}</td>
                            <td class="py-2.5 text-right">
                                <form action="{{ route('batch-orders.delete-item', [$batchOrder, $item]) }}" method="POST" class="inline" onsubmit="return confirm('Remove {{ $item->product->name }} (qty: {{ $item->quantity }})? Stock will be reduced by {{ $item->quantity }} units.')">
                                    @csrf @method('DELETE')
                                    <button type="submit" class="touch-target flex items-center justify-center p-1.5 rounded-lg text-gray-400 hover:text-rose-600 hover:bg-rose-50 transition-all" title="Remove item">
                                        <i class="bi bi-trash text-sm"></i>
                                    </button>
                                </form>
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                    <tfoot>
                        <tr class="border-t border-gray-200">
                            <td colspan="4" class="py-2.5 text-right font-bold text-gray-900">Total</td>
                            <td class="py-2.5 text-right font-bold text-gray-900">₹{{ number_format($batchOrder->total_cost) }}</td>
                            <td></td>
                        </tr>
                    </tfoot>
                </table>
            </div>

            <div class="mt-4 flex items-center gap-3 border-t border-gray-100 pt-4">
                <a href="{{ route('batch-orders.edit', $batchOrder) }}" class="inline-flex items-center gap-1.5 rounded-xl border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 shadow-sm hover:bg-gray-50 transition-all">
                    <i class="bi bi-pencil text-xs"></i> Edit Items
                </a>
                <a href="{{ route('batch-orders.edit', $batchOrder) }}#addItem" class="inline-flex items-center gap-1.5 rounded-xl border border-dashed border-gray-300 px-4 py-2 text-sm font-medium text-gray-600 hover:border-brand-400 hover:text-brand-600 hover:bg-brand-50/50 transition-all">
                    <i class="bi bi-plus-lg text-xs"></i> Add Product
                </a>
            </div>
        </x-card>
    </div>
</div>
@endsection
