@props(['variant' => 'primary', 'size' => 'md', 'icon' => null, 'type' => 'button'])

@php
    $variants = [
        'primary' => 'bg-emerald-600 text-white hover:bg-emerald-700 focus:ring-emerald-500 shadow-sm shadow-emerald-200',
        'secondary' => 'bg-gray-100 text-gray-700 hover:bg-gray-200 focus:ring-gray-400',
        'danger' => 'bg-rose-600 text-white hover:bg-rose-700 focus:ring-rose-500 shadow-sm shadow-rose-200',
        'ghost' => 'text-gray-600 hover:bg-gray-100 hover:text-gray-900 focus:ring-gray-400',
        'outline' => 'border border-gray-300 text-gray-700 hover:bg-gray-50 focus:ring-gray-400',
        'link' => 'text-emerald-600 hover:text-emerald-700 hover:underline p-0',
    ];
    $sizes = [
        'xs' => 'px-2.5 py-1.5 text-xs',
        'sm' => 'px-3 py-1.5 text-sm',
        'md' => 'px-4 py-2 text-sm',
        'lg' => 'px-5 py-2.5 text-base',
    ];
@endphp

<button type="{{ $type }}" {{ $attributes->merge([
    'class' => "inline-flex items-center justify-center gap-2 font-semibold rounded-xl transition-all duration-200 focus:outline-none focus:ring-2 focus:ring-offset-2 cursor-pointer {$variants[$variant]} {$sizes[$size]}"
]) }}>
    @if($icon)
        <i class="bi {{ $icon }} {{ $size === 'xs' ? 'text-xs' : 'text-sm' }}"></i>
    @endif
    {{ $slot }}
</button>
