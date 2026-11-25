<?php

use App\Livewire\Actions\Logout;
use Livewire\Volt\Component;

new class extends Component
{
    /**
     * Log the current user out of the application.
     */
    public function logout(Logout $logout): void
    {
        $logout();

        $this->redirect('/', navigate: true);
    }
}; ?>

@php
    $user = auth()->user();
    
    // Gunakan null-safe operator (?->) dan default value jika user belum login
    $roleLabel = ['owner' => 'Owner', 'partner' => 'Operational Partner', 'staff' => 'Staff'][$user?->role] ?? ucfirst((string) $user?->role);
    $userName = $user?->name ?? 'Guest';
    $userInitial = mb_strtoupper(mb_substr($userName, 0, 1));

    // Halaman Paket beralamat di bawah /master, jadi dibedakan dulu dari Data Master
    $onPackages = request()->routeIs('packages*') || request()->is('master/packages*');
    $onMaster = request()->routeIs('master.*') && ! $onPackages;
@endphp

<div @keydown.escape.window="open = false">

    {{-- Bar atas: selalu di layar kecil; di desktop hanya saat sidebar disembunyikan --}}
    <div class="fixed inset-x-0 top-0 z-30 flex h-14 items-center gap-3 border-b border-slate-100 bg-white/90 px-4 backdrop-blur"
         :class="collapsed ? '' : 'lg:hidden'">
        <button type="button" @click="open = true" class="-ml-1 rounded-lg p-2 text-slate-500 hover:bg-slate-100 lg:hidden" aria-label="Buka menu">
            <x-icon name="menu" class="h-6 w-6" />
        </button>
        <button type="button" @click="collapsed = false" class="-ml-1 hidden rounded-lg p-2 text-slate-500 hover:bg-slate-100 lg:inline-flex" aria-label="Tampilkan menu">
            <x-icon name="menu" class="h-6 w-6" />
        </button>
        <span class="grid h-7 w-7 place-items-center rounded-lg bg-indigo-600 text-white" aria-hidden="true">
            <x-application-logo class="h-4 w-4 fill-current" />
        </span>
        <span class="text-sm font-bold text-slate-900">Sunrise of Java</span>
    </div>

    {{-- Latar gelap saat menu terbuka di layar kecil --}}
    <div x-show="open" x-transition.opacity @click="open = false" style="display: none;"
         class="fixed inset-0 z-40 bg-slate-900/50 lg:hidden"></div>

    {{-- Sidebar --}}
    <aside :class="{ '!translate-x-0': open, 'lg:!translate-x-0': ! collapsed }"
           class="fixed inset-y-0 left-0 z-50 flex w-64 -translate-x-full flex-col border-r border-slate-100 bg-white shadow-[4px_0_24px_-12px_rgba(79,70,229,0.12)] transition-transform duration-200">

        {{-- Merek --}}
        <div class="flex h-[72px] shrink-0 items-center justify-between px-5">
            <a href="{{ route('dashboard') }}" wire:navigate class="flex items-center gap-3 rounded-lg focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500">
                <span class="grid h-10 w-10 shrink-0 place-items-center rounded-xl bg-indigo-600 text-white shadow-md shadow-indigo-600/25" aria-hidden="true">
                    <x-application-logo class="h-6 w-6 fill-current" />
                </span>
                <div class="leading-tight">
                    <div class="text-sm font-bold text-slate-900">Sunrise of Java</div>
                    <div class="text-xs font-medium text-indigo-600">Trip Calculator</div>
                </div>
            </a>

            {{-- Layar kecil: tutup menu --}}
            <button type="button" @click="open = false" class="rounded-lg p-1.5 text-slate-400 hover:bg-slate-100 hover:text-slate-600 lg:hidden" aria-label="Tutup menu">
                <x-icon name="close" class="h-5 w-5" />
            </button>

            {{-- Desktop: sembunyikan sidebar --}}
            <button type="button" @click="collapsed = true" title="Sembunyikan menu"
                    class="hidden rounded-lg p-1.5 text-slate-400 hover:bg-slate-100 hover:text-slate-600 lg:block" aria-label="Sembunyikan menu">
                <x-icon name="chevron-double-left" class="h-5 w-5" />
            </button>
        </div>

        {{-- Menu --}}
        <nav class="flex-1 space-y-6 overflow-y-auto px-4 py-3">
            <div class="space-y-1">
                <p class="px-3 pb-1 text-[11px] font-semibold uppercase tracking-[0.14em] text-slate-500">Operasional</p>
                <x-sidebar-link :href="route('dashboard')" icon="home" :active="request()->routeIs('dashboard')">Dashboard</x-sidebar-link>
                @can('trip.view')
                    <x-sidebar-link :href="route('trips.index')" icon="map" :active="request()->routeIs('trips.*')">Trip</x-sidebar-link>
                @endcan
                @can('report.view')
                    @can('view-finance')
                        <x-sidebar-link :href="route('reports')" icon="chart-bar" :active="request()->routeIs('reports*')">Laporan</x-sidebar-link>
                    @endcan
                @endcan
            </div>

            @can('master.view')
                <div class="space-y-1">
                    <p class="px-3 pb-1 text-[11px] font-semibold uppercase tracking-[0.14em] text-slate-500">Katalog</p>
                    <x-sidebar-link :href="route('packages')" icon="box" :active="$onPackages">Paket</x-sidebar-link>
                    <x-sidebar-link :href="route('master.vendors')" icon="database" :active="$onMaster">Data Master</x-sidebar-link>
                </div>
            @endcan

            @can('rules.manage')
                <div class="space-y-1">
                    <p class="px-3 pb-1 text-[11px] font-semibold uppercase tracking-[0.14em] text-slate-500">Pengaturan</p>
                    <x-sidebar-link :href="route('pricing-rules')" icon="tag" :active="request()->routeIs('pricing-rules')">Aturan Harga</x-sidebar-link>
                    <x-sidebar-link :href="route('profit-rules')" icon="tag" :active="request()->routeIs('profit-rules')">Aturan Profit</x-sidebar-link>
                </div>
            @endcan

            @can('audit.view')
                <div class="space-y-1">
                    <p class="px-3 text-xs font-semibold uppercase tracking-wider text-gray-400">Pengawasan</p>
                    <x-sidebar-link :href="route('audit')" icon="database" :active="request()->routeIs('audit')">Audit Log</x-sidebar-link>
                </div>
            @endcan
        </nav>

        {{-- Pengguna --}}
        <div class="shrink-0 space-y-1 border-t border-slate-100 p-4">
            <div class="mb-2 flex items-center gap-3 rounded-xl bg-slate-50 px-3 py-2.5">
                <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-gradient-to-br from-indigo-500 to-violet-500 text-sm font-bold text-white">
                    {{ $userInitial }}
                </div>
                <div class="min-w-0">
                    <div class="truncate text-sm font-semibold text-slate-900"
                         x-data="{{ json_encode(['name' => $userName]) }}"
                         x-text="name"
                         x-on:profile-updated.window="name = $event.detail.name"></div>
                    <div class="truncate text-xs text-slate-500">{{ $roleLabel }}</div>
                </div>
            </div>

            <x-sidebar-link :href="route('profile')" icon="user" :active="request()->routeIs('profile')">Profil</x-sidebar-link>

            <button type="button" wire:click="logout"
                    class="group flex w-full items-center gap-3 rounded-xl px-3 py-2.5 text-sm font-medium text-slate-600 transition-colors hover:bg-slate-100 hover:text-slate-900 focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500">
                <x-icon name="logout" class="h-5 w-5 shrink-0 text-slate-400 group-hover:text-slate-600" />
                Keluar
            </button>
        </div>
    </aside>
</div>