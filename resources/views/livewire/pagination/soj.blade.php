@if ($paginator->hasPages())
    @php($pageName = $paginator->getPageName())

    <nav role="navigation" aria-label="Navigasi halaman"
         class="flex flex-col items-center justify-between gap-3 border-t border-slate-100 px-5 py-4 sm:flex-row">
        <p class="text-xs text-slate-500">
            Menampilkan
            <span class="font-semibold text-slate-700">{{ $paginator->firstItem() }}–{{ $paginator->lastItem() }}</span>
            dari
            <span class="font-semibold text-slate-700">{{ $paginator->total() }}</span>
            trip
        </p>

        <div class="flex items-center gap-1">
            {{-- Sebelumnya --}}
            @if ($paginator->onFirstPage())
                <span class="grid h-8 w-8 place-items-center rounded-full text-slate-300" aria-disabled="true" aria-label="Sebelumnya">
                    <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M15 19l-7-7 7-7" /></svg>
                </span>
            @else
                <button type="button" wire:click="previousPage('{{ $pageName }}')" wire:loading.attr="disabled" aria-label="Sebelumnya"
                        class="grid h-8 w-8 place-items-center rounded-full text-slate-500 transition-colors hover:bg-indigo-50 hover:text-indigo-700 focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500">
                    <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M15 19l-7-7 7-7" /></svg>
                </button>
            @endif

            {{-- Nomor halaman --}}
            @foreach ($elements as $element)
                @if (is_string($element))
                    <span class="px-1 text-xs text-slate-400" aria-hidden="true">{{ $element }}</span>
                @endif

                @if (is_array($element))
                    @foreach ($element as $page => $url)
                        <span wire:key="paginator-{{ $pageName }}-page{{ $page }}">
                            @if ($page == $paginator->currentPage())
                                <span aria-current="page"
                                      class="grid h-8 min-w-[2rem] place-items-center rounded-full bg-indigo-600 px-2 text-xs font-semibold text-white shadow-md shadow-indigo-600/25">{{ $page }}</span>
                            @else
                                <button type="button" wire:click="gotoPage({{ $page }}, '{{ $pageName }}')" aria-label="Ke halaman {{ $page }}"
                                        class="grid h-8 min-w-[2rem] place-items-center rounded-full px-2 text-xs font-medium text-slate-600 transition-colors hover:bg-indigo-50 hover:text-indigo-700 focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500">{{ $page }}</button>
                            @endif
                        </span>
                    @endforeach
                @endif
            @endforeach

            {{-- Berikutnya --}}
            @if ($paginator->hasMorePages())
                <button type="button" wire:click="nextPage('{{ $pageName }}')" wire:loading.attr="disabled" aria-label="Berikutnya"
                        class="grid h-8 w-8 place-items-center rounded-full text-slate-500 transition-colors hover:bg-indigo-50 hover:text-indigo-700 focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500">
                    <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M9 5l7 7-7 7" /></svg>
                </button>
            @else
                <span class="grid h-8 w-8 place-items-center rounded-full text-slate-300" aria-disabled="true" aria-label="Berikutnya">
                    <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M9 5l7 7-7 7" /></svg>
                </span>
            @endif
        </div>
    </nav>
@endif