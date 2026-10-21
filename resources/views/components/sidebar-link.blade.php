@props(['icon', 'active' => false])

<a wire:navigate @if ($active) aria-current="page" @endif
   {{ $attributes->class([
        'group flex items-center gap-3 rounded-md px-3 py-2 text-sm font-medium transition-colors',
        'bg-indigo-50 text-indigo-700' => $active,
        'text-gray-600 hover:bg-gray-100 hover:text-gray-900' => ! $active,
   ]) }}>
    <x-icon :name="$icon" :class="$active ? 'h-5 w-5 shrink-0 text-indigo-600' : 'h-5 w-5 shrink-0 text-gray-400 group-hover:text-gray-600'" />
    <span>{{ $slot }}</span>
</a>