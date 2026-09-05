@props(['icon' => 'inbox', 'title' => 'No data found', 'description' => null, 'action' => null])

<div class="flex flex-col items-center justify-center py-12 px-4">
    <div class="flex h-16 w-16 items-center justify-center rounded-2xl bg-gray-100 text-gray-400 mb-4">
        <i class="bi bi-{{ $icon }} text-2xl"></i>
    </div>
    <h3 class="text-sm font-semibold text-gray-900 mb-1">{{ $title }}</h3>
    @if($description)
        <p class="text-xs text-gray-500 text-center max-w-sm mb-4">{{ $description }}</p>
    @endif
    @if($action)
        <div>{{ $action }}</div>
    @endif
</div>
