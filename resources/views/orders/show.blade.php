@extends('layouts.app')
@section('title', 'Order ' . ($order->order_number ?? '#' . $order->id))

@section('content')
<div class="grid grid-cols-1 gap-6 lg:grid-cols-4">
    <!-- Left Column -->
    <div class="lg:col-span-1 space-y-4">
        <x-card title="Order Details">
            <div class="space-y-3">
                <div><p class="text-[10px] font-bold uppercase tracking-wider text-gray-400 mb-0.5">Order #</p><p class="text-sm font-medium text-gray-900">{{ $order->order_number ?? '—' }}</p></div>
                <div><p class="text-[10px] font-bold uppercase tracking-wider text-gray-400 mb-0.5">Platform</p><x-badge variant="info">{{ $order->platform->name }}</x-badge></div>
                <div><p class="text-[10px] font-bold uppercase tracking-wider text-gray-400 mb-0.5">Customer</p><p class="text-sm text-gray-700">{{ $order->customer_name ?? '—' }}</p></div>
                <div><p class="text-[10px] font-bold uppercase tracking-wider text-gray-400 mb-0.5">Products</p><p class="text-sm text-gray-900">{{ $order->items->count() }} item(s), {{ $order->total_quantity }} unit(s)</p></div>
                <div class="border-t border-gray-100 pt-3 space-y-2">
                    <div class="flex justify-between text-sm"><span class="text-gray-500">Gross Revenue</span><span class="font-bold text-gray-900">₹{{ number_format($order->gross_revenue) }}</span></div>
                    <div class="flex justify-between text-sm"><span class="text-gray-500">Charges</span><span class="font-bold text-rose-600">-₹{{ number_format($order->total_charges) }}</span></div>
                    <div class="flex justify-between text-sm"><span class="font-medium text-gray-700">Net Revenue</span><span class="font-bold {{ $order->net_revenue >= 0 ? 'text-emerald-600' : 'text-rose-600' }}">₹{{ number_format($order->net_revenue) }}</span></div>
                </div>
                <div class="border-t border-gray-100 pt-3">
                    @php $sv = match($order->status) { 'delivered' => 'success', 'shipped','in_transit' => 'warning', default => 'default' }; @endphp
                    <x-badge :variant="$sv" size="md">Shipment: {{ ucfirst(str_replace('_', ' ', $order->status)) }}</x-badge>
                </div>
                @if($order->shipped_at)
                    <div><p class="text-[10px] font-bold uppercase tracking-wider text-gray-400 mb-0.5">Shipped At</p><p class="text-sm text-gray-700">{{ $order->shipped_at->format('d M Y, h:i A') }}</p></div>
                @endif
                <div><p class="text-[10px] font-bold uppercase tracking-wider text-gray-400 mb-0.5">Created</p><p class="text-sm text-gray-700">{{ $order->created_at->format('d M Y, h:i A') }}</p></div>
            </div>
        </x-card>

        <!-- Shipment Status Update -->
        @if(in_array($order->status, ['created', 'shipped', 'in_transit']))
        <x-card title="Update Shipment Status">
            <form action="{{ route('orders.update-shipment-status', $order) }}" method="POST" class="space-y-3">
                @csrf @method('PATCH')
                <select name="status" class="w-full rounded-xl border border-gray-200 bg-gray-50 px-3 py-2 text-sm focus:border-brand-500 focus:ring-1 focus:ring-brand-500 focus:outline-none" required>
                    <option value="">Select status...</option>
                    @if($order->status === 'created')<option value="shipped">Shipped</option>@endif
                    @if($order->status === 'shipped')<option value="in_transit">In Transit</option>@endif
                    @if($order->status === 'in_transit')<option value="delivered">Delivered</option>@endif
                </select>
                <button type="submit" class="w-full rounded-xl bg-brand-600 px-4 py-2.5 text-sm font-semibold text-white shadow-lg shadow-brand-200 hover:bg-brand-700 transition-all">
                    <i class="bi bi-check-lg me-1"></i>Update Shipment
                </button>
            </form>
        </x-card>
        @endif
    </div>

    <!-- Right Column: Line Items -->
    <div class="lg:col-span-3 space-y-4">
        @foreach($order->items as $item)
        <x-card>
            <div class="flex items-start justify-between gap-3 mb-3">
                <div class="min-w-0 flex-1">
                    <a href="{{ route('products.show', $item->product) }}" class="text-base font-bold text-gray-900 hover:text-brand-600 transition-colors break-words">{{ $item->product->name }}</a>
                    <p class="text-xs text-gray-400">{{ $item->product->sku }}</p>
                </div>
                @php
                    $statusVariant = match($item->status) {
                        'successful' => 'success',
                        'customer_return', 'rto', 'missing' => 'danger',
                        default => 'default'
                    };
                @endphp
                <div class="shrink-0">
                <x-badge :variant="$statusVariant" size="md">{{ ucfirst(str_replace('_', ' ', $item->status)) }}</x-badge>
                </div>
            </div>

            <div class="grid grid-cols-3 gap-4 mb-3">
                <div><p class="text-[10px] font-bold uppercase tracking-wider text-gray-400 mb-0.5">Qty</p><p class="text-sm font-medium text-gray-900">{{ $item->quantity }}</p></div>
                <div><p class="text-[10px] font-bold uppercase tracking-wider text-gray-400 mb-0.5">Unit Price</p><p class="text-sm font-medium text-gray-900">₹{{ number_format($item->selling_price) }}</p></div>
                <div><p class="text-[10px] font-bold uppercase tracking-wider text-gray-400 mb-0.5">Subtotal</p><p class="text-sm font-bold text-gray-900">₹{{ number_format($item->gross_revenue) }}</p></div>
            </div>

            {{-- Per-item charges --}}
            @if($item->charges->isNotEmpty())
            <div class="bg-gray-50 rounded-lg p-3 mb-3">
                <p class="text-[10px] font-bold uppercase tracking-wider text-gray-400 mb-2">Charges</p>
                @foreach($item->charges as $charge)
                <div class="flex justify-between text-xs mb-1">
                    <span class="text-gray-500 capitalize">{{ str_replace('_', ' ', $charge->charge_name) }}</span>
                    <span class="font-medium text-gray-700">₹{{ number_format($charge->amount, 2) }}</span>
                </div>
                @endforeach
                <div class="flex justify-between text-xs border-t border-gray-200 pt-1 mt-1">
                    <span class="font-bold text-gray-700">Total Charges</span>
                    <span class="font-bold text-rose-600">₹{{ number_format($item->total_charges, 2) }}</span>
                </div>
            </div>
            @endif

            {{-- Net for this item --}}
            <div class="flex justify-between text-sm mb-3 {{ $item->net_revenue >= 0 ? 'text-emerald-600' : 'text-rose-600' }}">
                <span class="font-medium">Net Revenue</span>
                <span class="font-bold">₹{{ number_format($item->net_revenue) }}</span>
            </div>

            {{-- Return details --}}
            @if($item->returnDetail)
            <div class="bg-rose-50 rounded-lg p-3 mb-3">
                <p class="text-[10px] font-bold uppercase tracking-wider text-rose-400 mb-2">Return Details</p>
                <div class="grid grid-cols-2 gap-2 text-xs">
                    <div><span class="text-gray-500">Type:</span> <span class="font-medium">{{ ucfirst(str_replace('_', ' ', $item->returnDetail->return_type)) }}</span></div>
                    <div><span class="text-gray-500">Condition:</span> <span class="font-medium">{{ $item->returnDetail->condition ? ucfirst($item->returnDetail->condition) : '—' }}</span></div>
                    <div><span class="text-gray-500">Charges:</span> <span class="font-medium">₹{{ number_format($item->returnDetail->return_charges) }}</span></div>
                </div>
            </div>
            @endif

            {{-- Item status update (if pending) --}}
            @if($item->status === 'pending')
            <div class="border-t border-gray-100 pt-3">
                <form action="{{ route('orders.update-item-status', [$order, $item]) }}" method="POST" class="flex flex-wrap gap-2 items-end">
                    @csrf @method('PATCH')
                    <div class="flex-1 min-w-[140px]">
                        <label class="block text-[10px] font-bold uppercase tracking-wider text-gray-400 mb-1">Outcome</label>
                        <select name="status" class="w-full rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm focus:border-brand-500 focus:ring-1 focus:ring-brand-500 focus:outline-none" required>
                            <option value="successful">Successful</option>
                            <option value="customer_return">Customer Return</option>
                            <option value="rto">RTO</option>
                            <option value="missing">Missing</option>
                        </select>
                    </div>
                    <div class="return-fields hidden flex-1 min-w-[140px]">
                        <label class="block text-[10px] font-bold uppercase tracking-wider text-gray-400 mb-1">Condition</label>
                        <select name="condition" class="w-full rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm focus:border-brand-500 focus:ring-1 focus:ring-brand-500 focus:outline-none">
                            <option value="sellable">Sellable (restock)</option>
                            <option value="damaged">Damaged (loss)</option>
                        </select>
                    </div>
                    <div class="return-fields hidden min-w-[120px]">
                        <label class="block text-[10px] font-bold uppercase tracking-wider text-gray-400 mb-1">Return Charges</label>
                        <input type="number" name="return_charges" class="w-full rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm focus:border-brand-500 focus:ring-1 focus:ring-brand-500 focus:outline-none" placeholder="₹0" step="0.01" value="0">
                    </div>
                    <button type="submit" class="rounded-lg bg-brand-600 px-4 py-2 text-sm font-semibold text-white hover:bg-brand-700 transition-all">
                        Update
                    </button>
                </form>
            </div>
            @endif
        </x-card>
        @endforeach
    </div>
</div>

@section('scripts')
<script>
document.querySelectorAll('select[name="status"]').forEach(sel => {
    sel.addEventListener('change', function() {
        const card = this.closest('.item-row, .rounded-xl, [class*="card"]') || this.closest('form').parentElement;
        const returnFields = card.querySelectorAll('.return-fields');
        const isReturn = ['customer_return', 'rto', 'missing'].includes(this.value);
        returnFields.forEach(el => el.classList.toggle('hidden', !isReturn));
    });
});
</script>
@endsection
@endsection
