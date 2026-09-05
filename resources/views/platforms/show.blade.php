@extends('layouts.app')
@section('title', $platform->name)

@section('content')
<div class="grid grid-cols-1 gap-6 lg:grid-cols-4">
    <div class="lg:col-span-1 space-y-4">
        <x-card title="Platform Details">
            <div class="space-y-3">
                <div><p class="text-[10px] font-bold uppercase tracking-wider text-gray-400 mb-0.5">Name</p><p class="text-sm font-medium text-gray-900">{{ $platform->name }}</p></div>
                <div><p class="text-[10px] font-bold uppercase tracking-wider text-gray-400 mb-0.5">Slug</p><code class="text-xs bg-gray-100 px-1.5 py-0.5 rounded-md text-gray-600">{{ $platform->slug }}</code></div>
                <div><p class="text-[10px] font-bold uppercase tracking-wider text-gray-400 mb-0.5">Status</p>
                    @if($platform->is_active)<x-badge variant="success">Active</x-badge>@else<x-badge variant="default">Inactive</x-badge>@endif
                </div>
                <div><p class="text-[10px] font-bold uppercase tracking-wider text-gray-400 mb-0.5">Notes</p><p class="text-sm text-gray-700">{{ $platform->notes ?? '—' }}</p></div>
            </div>
        </x-card>
        <x-card title="Charge Structure">
            @if($platform->charge_structure)
                <div class="space-y-2">
                    @foreach($platform->charge_structure as $key => $value)
                    <div class="flex justify-between text-sm">
                        <span class="text-gray-500 capitalize">{{ str_replace('_', ' ', $key) }}</span>
                        <span class="font-medium text-gray-900">{{ is_numeric($value) && str_contains($key, 'percent') ? $value . '%' : '₹' . number_format($value, 2) }}</span>
                    </div>
                    @endforeach
                </div>
            @else
                <p class="text-sm text-gray-400 text-center py-2">No charges configured.</p>
            @endif
        </x-card>
    </div>
    <div class="lg:col-span-3 space-y-4">
        <x-card title="Recent Orders">
            @if($platform->orders->isEmpty())
                <x-empty-state icon="receipt" title="No orders" description="No orders on this platform yet." />
            @else
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="border-b border-gray-100">
                                <th class="pb-2.5 text-left text-[10px] font-bold uppercase tracking-wider text-gray-400">Order</th>
                                <th class="pb-2.5 text-left text-[10px] font-bold uppercase tracking-wider text-gray-400">Product</th>
                                <th class="pb-2.5 text-right text-[10px] font-bold uppercase tracking-wider text-gray-400">Qty</th>
                                <th class="pb-2.5 text-right text-[10px] font-bold uppercase tracking-wider text-gray-400">Amount</th>
                                <th class="pb-2.5 text-center text-[10px] font-bold uppercase tracking-wider text-gray-400">Status</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-50">
                            @foreach($platform->orders->take(10) as $order)
                            <tr class="hover:bg-gray-50/50 transition-colors">
                                <td class="py-2.5"><a href="{{ route('orders.show', $order) }}" class="font-medium text-brand-600 hover:text-brand-700">{{ $order->order_number ?? '#' . $order->id }}</a></td>
                                <td class="py-2.5 text-gray-600 text-xs">{{ $order->items->pluck('product.name')->implode(', ') }}</td>
                                <td class="py-2.5 text-right text-gray-600">{{ $order->total_quantity }}</td>
                                <td class="py-2.5 text-right font-medium text-gray-900">₹{{ number_format($order->gross_revenue) }}</td>
                                <td class="py-2.5 text-center">
                                    @php $sv = match($order->status) { 'delivered' => 'success', 'shipped','in_transit' => 'warning', default => 'default' }; @endphp
                                    <x-badge :variant="$sv">{{ ucfirst($order->status) }}</x-badge>
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </x-card>
        <x-card title="Payment History">
            @if($platform->payments->isEmpty())
                <x-empty-state icon="cash-stack" title="No payments" description="No payments received yet." />
            @else
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="border-b border-gray-100">
                                <th class="pb-2.5 text-left text-[10px] font-bold uppercase tracking-wider text-gray-400">Date</th>
                                <th class="pb-2.5 text-right text-[10px] font-bold uppercase tracking-wider text-gray-400">Amount</th>
                                <th class="pb-2.5 text-left text-[10px] font-bold uppercase tracking-wider text-gray-400">Notes</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-50">
                            @foreach($platform->payments as $payment)
                            <tr class="hover:bg-gray-50/50 transition-colors">
                                <td class="py-2.5 text-gray-600">{{ $payment->payment_date->format('d M Y') }}</td>
                                <td class="py-2.5 text-right font-bold text-emerald-600">₹{{ number_format($payment->amount) }}</td>
                                <td class="py-2.5 text-gray-500 text-xs">{{ $payment->notes }}</td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </x-card>
    </div>
</div>
@endsection
