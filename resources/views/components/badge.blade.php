@props(['variant' => 'default', 'size' => 'sm'])

@php
    $variants = [
        'default' => 'bg-gray-100 text-gray-700',
        'success' => 'bg-emerald-50 text-emerald-700 ring-1 ring-emerald-600/20',
        'warning' => 'bg-amber-50 text-amber-700 ring-1 ring-amber-600/20',
        'danger' => 'bg-rose-50 text-rose-700 ring-1 ring-rose-600/20',
        'info' => 'bg-sky-50 text-sky-700 ring-1 ring-sky-600/20',
        'purple' => 'bg-violet-50 text-violet-700 ring-1 ring-violet-600/20',
        'primary' => 'bg-emerald-500 text-white',
        'outline' => 'bg-transparent text-gray-600 ring-1 ring-gray-300',
    ];
    $sizes = [
        'xs' => 'px-1.5 py-0.5 text-[10px]',
        'sm' => 'px-2 py-0.5 text-xs',
        'md' => 'px-2.5 py-1 text-sm',
    ];
@endphp

<span class="inline-flex items-center font-semibold rounded-full {{ $variants[$variant] ?? $variants['default'] }} {{ $sizes[$size] ?? $sizes['sm'] }}">
    {{ $slot }}
</span>
