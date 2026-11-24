@php
    $shortcuts = [
        ['can' => 'trip.create', 'route' => 'trips.create', 'icon' => 'plus', 'title' => 'Trip baru', 'desc' => 'Buat estimasi trip dari paket atau dari nol.'],
        ['can' => 'trip.view', 'route' => 'trips.index', 'icon' => 'map', 'title' => 'Daftar trip', 'desc' => 'Lihat, cari, dan lanjutkan trip yang ada.'],
        ['can' => 'master.view', 'route' => 'packages', 'icon' => 'box', 'title' => 'Paket', 'desc' => 'Template paket wisata dan versinya.'],
        ['can' => 'master.view', 'route' => 'master.vendors', 'icon' => 'database', 'title' => 'Data Master', 'desc' => 'Vendor, hotel, kendaraan, destinasi, dan tarif.'],
        ['can' => 'rules.manage', 'route' => 'pricing-rules', 'icon' => 'tag', 'title' => 'Aturan Harga', 'desc' => 'Margin, pembulatan, dan batas minimum.'],
    ];
@endphp

<x-app-layout>
    <div class="py-8">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">

            <div class="bg-white shadow sm:rounded-lg p-6">
                <h2 class="text-xl font-semibold text-gray-800">Halo, {{ auth()->user()->name }}</h2>
                <p class="mt-1 text-sm text-gray-500">Pilih menu di sebelah kiri, atau mulai dari pintasan di bawah ini.</p>
            </div>

            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-3">
                @foreach ($shortcuts as $s)
                    @can($s['can'])
                        <a href="{{ route($s['route']) }}" wire:navigate
                           class="group flex items-start gap-4 rounded-lg bg-white p-5 shadow transition hover:shadow-md">
                            <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-indigo-50 text-indigo-600 group-hover:bg-indigo-100">
                                <x-icon :name="$s['icon']" class="h-5 w-5" />
                            </span>
                            <span>
                                <span class="block text-sm font-semibold text-gray-800">{{ $s['title'] }}</span>
                                <span class="mt-0.5 block text-sm text-gray-500">{{ $s['desc'] }}</span>
                            </span>
                        </a>
                    @endcan
                @endforeach
            </div>
        </div>
    </div>
</x-app-layout>