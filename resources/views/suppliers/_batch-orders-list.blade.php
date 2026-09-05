@foreach($batchOrders as $batch)
<div class="batch-order-card rounded-xl border border-gray-100 bg-gray-50/50 p-4 hover:bg-gray-50 transition-colors">
    <div class="flex items-start justify-between mb-3">
        <div>
            <div class="flex items-center gap-2 flex-wrap">
                <a href="{{ route('batch-orders.show', $batch) }}" class="font-semibold text-gray-900 hover:text-brand-600 transition-colors">
                    Batch Order #{{ $batch->id }}
                </a>
                <x-badge variant="info" size="xs">{{ $batch->items->count() }} products</x-badge>
            </div>
            <p class="text-xs text-gray-500 mt-1">{{ $batch->order_date->format('d M Y, l') }}</p>
        </div>
        <div class="text-right shrink-0 ml-2">
            <div class="text-lg font-bold text-gray-900">₹{{ number_format($batch->total_cost) }}</div>
            @if($batch->notes)
                <p class="text-[11px] text-gray-500 mt-0.5">{{ Str::limit($batch->notes, 30) }}</p>
            @endif
        </div>
    </div>

    {{-- Items in this batch --}}
    {{-- Mobile: simple list --}}
    <div class="md:hidden space-y-2">
        @foreach($batch->items as $item)
        <a href="{{ route('products.show', $item->product) }}" class="flex items-center justify-between p-2 bg-white rounded-lg border border-gray-50">
            <div class="min-w-0 flex-1">
                <p class="text-xs font-medium text-brand-600 truncate">{{ $item->product->name }}</p>
                <p class="text-[10px] text-gray-400">{{ $item->quantity }} × ₹{{ number_format($item->unit_cost) }}</p>
            </div>
            <p class="text-xs font-bold text-gray-900 shrink-0 ml-2">₹{{ number_format($item->total_cost) }}</p>
        </a>
        @endforeach
    </div>

    {{-- Desktop: table --}}
    <div class="hidden md:block bg-white rounded-lg border border-gray-100 overflow-hidden">
        <table class="w-full text-xs">
            <thead>
                <tr class="border-b border-gray-100 bg-gray-50">
                    <th class="px-3 py-2 text-left font-semibold text-gray-500">Product</th>
                    <th class="px-3 py-2 text-right font-semibold text-gray-500">Qty</th>
                    <th class="px-3 py-2 text-right font-semibold text-gray-500">Unit Cost</th>
                    <th class="px-3 py-2 text-right font-semibold text-gray-500">Total</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-50">
                @foreach($batch->items as $item)
                <tr>
                    <td class="px-3 py-2">
                        <a href="{{ route('products.show', $item->product) }}" class="font-medium text-brand-600 hover:text-brand-700">{{ $item->product->name }}</a>
                        <span class="text-gray-400 ml-1">{{ $item->product->sku }}</span>
                    </td>
                    <td class="px-3 py-2 text-right text-gray-600">{{ $item->quantity }}</td>
                    <td class="px-3 py-2 text-right text-gray-600">₹{{ number_format($item->unit_cost) }}</td>
                    <td class="px-3 py-2 text-right font-medium text-gray-900">₹{{ number_format($item->total_cost) }}</td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>
@endforeach
