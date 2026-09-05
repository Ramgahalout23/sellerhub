@extends('layouts.app')
@section('title', 'Low Stock Alert')
@section('subtitle', $products->count() . ' products below threshold')

@section('content')
<x-card padding="false">
    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead>
                <tr class="border-b border-gray-100">
                    <th class="px-5 py-3 text-left text-[10px] font-bold uppercase tracking-wider text-gray-400">Product</th>
                    <th class="px-5 py-3 text-left text-[10px] font-bold uppercase tracking-wider text-gray-400">SKU</th>
                    <th class="px-5 py-3 text-right text-[10px] font-bold uppercase tracking-wider text-gray-400">Stock</th>
                    <th class="px-5 py-3 text-right text-[10px] font-bold uppercase tracking-wider text-gray-400">Reorder At</th>
                    <th class="px-5 py-3 text-left text-[10px] font-bold uppercase tracking-wider text-gray-400">Suppliers</th>
                    <th class="px-5 py-3 text-right text-[10px] font-bold uppercase tracking-wider text-gray-400"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-50">
                @forelse($products as $product)
                <tr class="hover:bg-gray-50/50 transition-colors">
                    <td class="px-5 py-3.5"><a href="{{ route('products.show', $product) }}" class="font-semibold text-gray-900 hover:text-brand-600 transition-colors">{{ $product->name }}</a></td>
                    <td class="px-5 py-3.5"><code class="text-xs bg-gray-100 px-1.5 py-0.5 rounded-md text-gray-600">{{ $product->sku }}</code></td>
                    <td class="px-5 py-3.5 text-right">
                        @if($product->stock_quantity === 0)<x-badge variant="danger">Out of Stock</x-badge>@else<x-badge variant="warning">{{ $product->stock_quantity }} left</x-badge>@endif
                    </td>
                    <td class="px-5 py-3.5 text-right text-gray-600">{{ $product->reorder_threshold }}</td>
                    <td class="px-5 py-3.5">
                        @foreach($product->suppliers as $s)<x-badge variant="outline" class="mr-1">{{ $s->name }}</x-badge>@endforeach
                    </td>
                    <td class="px-5 py-3.5 text-right"><a href="{{ route('batch-orders.create') }}" class="rounded-lg bg-brand-600 px-3 py-1.5 text-xs font-semibold text-white hover:bg-brand-700 transition-colors">Reorder</a></td>
                </tr>
                @empty
                <tr><td colspan="6"><x-empty-state icon="check-circle" title="All stocked up" description="No products below reorder threshold." /></td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</x-card>
@endsection
