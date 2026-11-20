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
    <div class="fixed inset-x-0 top-0 z-30 flex h-14 items-center gap-3 border-b border-gray-200 bg-white px-4"
         :class="collapsed ? '' : 'lg:hidden'">
        <button type="button" @click="open = true" class="-ml-1 rounded-md p-2 text-gray-500 hover:bg-gray-100 lg:hidden" aria-label="Buka menu">
            <x-icon name="menu" class="h-6 w-6" />
        </button>
        <button type="button" @click="collapsed = false" class="-ml-1 hidden rounded-md p-2 text-gray-500 hover:bg-gray-100 lg:inline-flex" aria-label="Tampilkan menu">
            <x-icon name="menu" class="h-6 w-6" />
        </button>
        <span class="text-sm font-semibold text-gray-800">Sunrise of Java</span>
    </div>

    {{-- Latar gelap saat menu terbuka di layar kecil --}}
    <div x-show="open" x-transition.opacity @click="open = false" style="display: none;"
         class="fixed inset-0 z-40 bg-gray-900/50 lg:hidden"></div>

    {{-- Sidebar --}}
    <aside :class="{ '!translate-x-0': open, 'lg:!translate-x-0': ! collapsed }"
           class="fixed inset-y-0 left-0 z-50 flex w-64 -translate-x-full flex-col border-r border-gray-200 bg-white transition-transform duration-200">

        {{-- Merek --}}
        <div class="flex h-16 shrink-0 items-center justify-between border-b border-gray-200 px-5">
            <a href="{{ route('dashboard') }}" wire:navigate class="flex items-center gap-3">
                <x-application-logo class="h-8 w-8 fill-current text-indigo-600" />
                <div class="leading-tight">
                    <div class="text-sm font-semibold text-gray-800">Sunrise of Java</div>
                    <div class="text-xs text-gray-500">Trip Calculator</div>
                </div>
            </a>

            {{-- Layar kecil: tutup menu --}}
            <button type="button" @click="open = false" class="rounded-md p-1.5 text-gray-400 hover:bg-gray-100 lg:hidden" aria-label="Tutup menu">
                <x-icon name="close" class="h-5 w-5" />
            </button>

            {{-- Desktop: sembunyikan sidebar --}}
            <button type="button" @click="collapsed = true" title="Sembunyikan menu"
                    class="hidden rounded-md p-1.5 text-gray-400 hover:bg-gray-100 hover:text-gray-600 lg:block" aria-label="Sembunyikan menu">
                <x-icon name="chevron-double-left" class="h-5 w-5" />
            </button>
        </div>

        {{-- Menu --}}
        <nav class="flex-1 space-y-6 overflow-y-auto px-3 py-4">
            <div class="space-y-1">
                <p class="px-3 text-xs font-semibold uppercase tracking-wider text-gray-400">Operasional</p>
                <x-sidebar-link :href="route('dashboard')" icon="home" :active="request()->routeIs('dashboard')">Dashboard</x-sidebar-link>
                @can('trip.view')
                    <x-sidebar-link :href="route('trips.index')" icon="map" :active="request()->routeIs('trips.*')">Trip</x-sidebar-link>
                @endcan
                @can('report.view')
                    @can('view-finance')
                        <x-sidebar-link :href="route('reports')" icon="chart" :active="request()->routeIs('reports*')">Laporan</x-sidebar-link>
                    @endcan
                @endcan
            </div>

            @can('master.view')
                <div class="space-y-1">
                    <p class="px-3 text-xs font-semibold uppercase tracking-wider text-gray-400">Katalog</p>
                    <x-sidebar-link :href="route('packages')" icon="box" :active="$onPackages">Paket</x-sidebar-link>
                    <x-sidebar-link :href="route('master.vendors')" icon="database" :active="$onMaster">Data Master</x-sidebar-link>
                </div>
            @endcan

            @can('rules.manage')
                <div class="space-y-1">
                    <p class="px-3 text-xs font-semibold uppercase tracking-wider text-gray-400">Pengaturan</p>
                    <x-sidebar-link :href="route('pricing-rules')" icon="tag" :active="request()->routeIs('pricing-rules')">Aturan Harga</x-sidebar-link>
                    <x-sidebar-link :href="route('profit-rules')" icon="tag" :active="request()->routeIs('profit-rules')">Aturan Profit</x-sidebar-link>
                </div>
            @endcan

        </nav>

        {{-- Pengguna --}}
        <div class="shrink-0 space-y-1 border-t border-gray-200 p-3">
            <div class="flex items-center gap-3 px-3 py-2">
                <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-indigo-100 text-sm font-semibold text-indigo-700">
                    {{-- PERUBAHAN 1: Menggunakan $userInitial --}}
                    {{ $userInitial }}
                </div>
                <div class="min-w-0">
                    {{-- PERUBAHAN 2: Menggunakan $userName --}}
                    <div class="truncate text-sm font-medium text-gray-800"
                         x-data="{{ json_encode(['name' => $userName]) }}"
                         x-text="name"
                         x-on:profile-updated.window="name = $event.detail.name"></div>
                    <div class="truncate text-xs text-gray-500">{{ $roleLabel }}</div>
                </div>
            </div>

            <x-sidebar-link :href="route('profile')" icon="user" :active="request()->routeIs('profile')">Profil</x-sidebar-link>

            <button type="button" wire:click="logout"
                    class="group flex w-full items-center gap-3 rounded-md px-3 py-2 text-sm font-medium text-gray-600 transition-colors hover:bg-gray-100 hover:text-gray-900">
                <x-icon name="logout" class="h-5 w-5 shrink-0 text-gray-400 group-hover:text-gray-600" />
                Keluar
            </button>
        </div>
    </aside>
</div>