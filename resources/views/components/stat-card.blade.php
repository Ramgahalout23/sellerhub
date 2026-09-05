@props(['title', 'value', 'icon' => null, 'color' => 'primary', 'subtitle' => null])

@php
    $colors = [
        'primary' => ['bg' => 'bg-emerald-50', 'text' => 'text-emerald-600', 'icon' => 'bg-emerald-100'],
        'success' => ['bg' => 'bg-emerald-50', 'text' => 'text-emerald-600', 'icon' => 'bg-emerald-100'],
        'warning' => ['bg' => 'bg-amber-50', 'text' => 'text-amber-600', 'icon' => 'bg-amber-100'],
        'danger' => ['bg' => 'bg-rose-50', 'text' => 'text-rose-600', 'icon' => 'bg-rose-100'],
        'info' => ['bg' => 'bg-sky-50', 'text' => 'text-sky-600', 'icon' => 'bg-sky-100'],
        'purple' => ['bg' => 'bg-violet-50', 'text' => 'text-violet-600', 'icon' => 'bg-violet-100'],
    ];
    $c = $colors[$color] ?? $colors['primary'];
@endphp

<div class="group relative overflow-hidden rounded-2xl border border-gray-100 bg-white p-4 lg:p-5 shadow-sm">
    <div class="flex items-start justify-between gap-2">
        <div class="flex-1 min-w-0">
            <p class="text-[10px] lg:text-xs font-semibold uppercase tracking-wider text-gray-400 mb-1">{{ $title }}</p>
            <p class="text-xl lg:text-2xl font-bold text-gray-900">{{ $value }}</p>
            @if(isset($subtitle) && $subtitle)
                <div class="text-xs text-gray-400 mt-1.5 leading-relaxed">{{ $subtitle }}</div>
            @endif
        </div>
        @if($icon)
            <div class="flex-shrink-0 ml-3">
                <div class="flex h-12 w-12 items-center justify-center rounded-xl {{ $c['icon'] }} {{ $c['text'] }} transition-transform duration-300 group-hover:scale-110">
                    <i class="bi bi-{{ $icon }} text-xl"></i>
                </div>
            </div>
        @endif
    </div>
</div>
