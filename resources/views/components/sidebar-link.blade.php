@props(['icon', 'active' => false])

@php
    // Memaksa agar jika icon bernama 'laporan' atau 'report', gunakan 'chart-bar'
    // Ini adalah pengaman agar ikon selalu muncul meskipun namanya sedikit berbeda
    $iconName = in_array($icon, ['laporan', 'report', 'reports']) ? 'chart-bar' : $icon;
@endphp

<a wire:navigate @if ($active) aria-current="page" @endif
   {{ $attributes->class([
        'group flex items-center gap-3 rounded-xl px-3.5 py-2.5 text-sm font-semibold transition-all duration-200 focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500',
        
        'bg-indigo-50/80 text-indigo-700 shadow-sm ring-1 ring-indigo-100' => $active,
        
        'text-slate-600 hover:bg-slate-50 hover:text-indigo-700' => ! $active,
   ]) }}>
    
    <x-icon :name="$iconName" 
            class="h-5 w-5 shrink-0 transition-colors duration-200 {{ $active ? 'text-indigo-600' : 'text-slate-400 group-hover:text-indigo-600' }}" />
            
    <span>{{ $slot }}</span>
</a>