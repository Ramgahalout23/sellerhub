{{-- Reusable rows for the product sale-history table (initial render + "load more") --}}
@foreach($recent_orders as $item)
<tr class="hover:bg-gray-50/50 transition-colors">
    <td class="py-2.5"><a href="{{ route('orders.show', $item->order) }}" class="font-medium text-brand-600 hover:text-brand-700">{{ $item->order->order_number ?? '#' . $item->order->id }}</a></td>
    <td class="py-2.5"><x-badge variant="info">{{ $item->order->platform->name ?? '—' }}</x-badge></td>
    <td class="py-2.5 text-gray-600">{{ $item->order->customer_name ?? '—' }}</td>
    <td class="py-2.5 text-right text-gray-600">{{ $item->quantity }}</td>
    <td class="py-2.5 text-right font-medium text-gray-900">₹{{ number_format($item->selling_price * $item->quantity) }}</td>
    <td class="py-2.5 text-center">
        @php $sv = match($item->status) { 'successful' => 'success', 'customer_return','rto','missing' => 'danger', default => 'default' }; @endphp
        <x-badge :variant="$sv">{{ ucfirst(str_replace('_', ' ', $item->status)) }}</x-badge>
    </td>
</tr>
@endforeach
