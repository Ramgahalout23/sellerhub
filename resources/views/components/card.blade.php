@props(['title' => null, 'subtitle' => null, 'action' => null, 'padding' => true])

<div {{ $attributes->merge(['class' => 'rounded-2xl border border-gray-100 bg-white shadow-sm']) }}>
    @if($title || $action)
        <div class="flex flex-col gap-3 border-b border-gray-100 px-5 py-4 sm:flex-row sm:items-center sm:justify-between">
            <div class="min-w-0">
                @if($title)
                    <h3 class="text-sm font-bold text-gray-900">{{ $title }}</h3>
                @endif
                @if($subtitle)
                    <p class="text-xs text-gray-500 mt-0.5">{{ $subtitle }}</p>
                @endif
            </div>
            @if($action)
                <div class="sm:shrink-0 sm:ml-4">{{ $action }}</div>
            @endif
        </div>
    @endif
    <div class="{{ $padding ? 'p-3 lg:p-5' : '' }}">
        {{ $slot }}
    </div>
</div>
