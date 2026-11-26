@php
    $canPrice = auth()->user()->can('view-costs');

    // Ikon dan warna kotak ikon per tipe trip
    $typeStyle = [
        'open_trip' => ['users', 'bg-indigo-50 text-indigo-600'],
        'private' => ['user', 'bg-violet-50 text-violet-600'],
        'one_day' => ['clock', 'bg-sky-50 text-sky-600'],
        'group_tour' => ['users', 'bg-emerald-50 text-emerald-600'],
        'honeymoon' => ['heart', 'bg-rose-50 text-rose-600'],
        'jeep' => ['map', 'bg-amber-50 text-amber-600'],
        'custom' => ['tag', 'bg-slate-100 text-slate-600'],
    ];

    // Menghitung status secara dinamis dari collection $trips
    $countDraft = $trips->where('status', 'draft')->count();
    $countEstimated = $trips->where('status', 'estimated')->count();
    $countConfirmed = $trips->whereIn('status', ['confirmed', 'deposit_paid', 'paid'])->count();

    // Mapping Kotak Ringkasan (Status Dinamis)
    $statCards = [
        ['label' => 'Total Trip', 'value' => $trips->total(), 'icon' => 'map', 'tone' => 'bg-slate-100 text-slate-600'],
        ['label' => 'Draft', 'value' => $countDraft, 'icon' => 'pencil', 'tone' => 'bg-amber-50 text-amber-600'],
        ['label' => 'Estimated', 'value' => $countEstimated, 'icon' => 'tag', 'tone' => 'bg-sky-50 text-sky-600'],
        ['label' => 'Confirmed', 'value' => $countConfirmed, 'icon' => 'check-circle', 'tone' => 'bg-emerald-50 text-emerald-600'],
    ];

    $th = 'px-3 py-3.5 text-[11px] font-semibold uppercase tracking-[0.1em] text-slate-500';
    $select = 'rounded-xl border border-slate-200 bg-slate-50 py-2.5 pl-3.5 pr-9 text-sm text-slate-700 focus:border-indigo-500 focus:bg-white focus:ring-2 focus:ring-indigo-500/20';

    // Mapping Warna Status
    $statusStyles = [
        'draft' => 'bg-amber-50 text-amber-700 ring-amber-200',
        'estimated' => 'bg-sky-50 text-sky-700 ring-sky-200',
        'confirmed' => 'bg-emerald-50 text-emerald-700 ring-emerald-200',
        'deposit_paid' => 'bg-teal-50 text-teal-700 ring-teal-200',
        'paid' => 'bg-green-50 text-green-700 ring-green-200',
        'ongoing' => 'bg-violet-50 text-violet-700 ring-violet-200',
        'completed' => 'bg-slate-100 text-slate-700 ring-slate-200',
        'reconciled' => 'bg-indigo-50 text-indigo-700 ring-indigo-200',
        'cancelled' => 'bg-rose-50 text-rose-700 ring-rose-200',
    ];
@endphp

<div class="py-8">
    <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-5">
        <div class="flex flex-wrap items-start justify-between gap-4">
            <div>
                <h1 class="text-2xl font-extrabold tracking-tight text-slate-900">Trip</h1>
                <p class="mt-1 text-sm text-slate-500">Semua trip beserta jadwal, jumlah peserta, dan status terkininya.</p>
            </div>
            @can('trip.create')
                <a href="{{ route('trips.create') }}" wire:navigate
                   class="inline-flex shrink-0 items-center rounded-xl bg-gradient-to-r from-indigo-600 to-violet-600 px-4 py-2.5 text-sm font-semibold text-white shadow-lg shadow-indigo-600/25 transition hover:from-indigo-700 hover:to-violet-700 focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500 focus-visible:ring-offset-2">+ Trip baru</a>
            @endcan
        </div>

        @if (session('status'))
            <div class="rounded-xl bg-emerald-50 p-3.5 text-sm text-emerald-800 ring-1 ring-emerald-100">{{ session('status') }}</div>
        @endif

        @if ($statuses !== '' || $startFrom !== '' || $startTo !== '')
            <div class="flex flex-wrap items-center justify-between gap-2 rounded-lg bg-indigo-50 p-3 text-xs text-indigo-800">
                <span>Filter dashboard: {{ $startFrom ?: '-' }} s/d {{ $startTo ?: '-' }}
                    @if ($statuses) / {{ collect(explode(',', $statuses))->map(fn ($s) => \App\Models\Trip::STATUS[$s] ?? $s)->implode(', ') }} @endif
                </span>
                <a href="{{ route('trips.index') }}" wire:navigate class="font-semibold underline">Hapus filter dashboard</a>
            </div>
        @endif

        {{-- Ringkasan (Status Dinamis) --}}
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
            @foreach ($statCards as $c)
                <div class="rounded-2xl bg-white p-5 shadow-[0_8px_30px_-14px_rgba(79,70,229,0.18)] ring-1 ring-slate-100">
                    <div class="flex items-start justify-between gap-3">
                        <p class="text-[11px] font-semibold uppercase tracking-[0.1em] text-slate-500">{{ $c['label'] }}</p>
                        <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl {{ $c['tone'] }}">
                            <x-icon :name="$c['icon']" class="h-[18px] w-[18px]" />
                        </span>
                    </div>
                    <p class="mt-3 text-2xl font-extrabold tracking-tight tabular-nums text-slate-900">{{ number_format($c['value'], 0, ',', '.') }} Trip</p>
                </div>
            @endforeach
        </div>

        {{-- Filter --}}
        <div class="flex flex-wrap items-center gap-3 rounded-2xl bg-white p-4 shadow-[0_8px_30px_-14px_rgba(79,70,229,0.18)] ring-1 ring-slate-100">
            <div class="relative min-w-[260px] flex-1">
                <x-icon name="search" class="pointer-events-none absolute left-3.5 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" />
                <input type="search" wire:model.live.debounce.300ms="search" placeholder="Cari kode, nama trip, atau customer..."
                       aria-label="Cari trip"
                       class="w-full rounded-xl border border-slate-200 bg-slate-50 py-2.5 pl-10 pr-3 text-sm text-slate-900 placeholder:text-slate-400 focus:border-indigo-500 focus:bg-white focus:ring-2 focus:ring-indigo-500/20">
            </div>

            <select wire:model.live="statusFilter" aria-label="Filter status" class="{{ $select }}">
                <option value="">Semua status</option>
                @foreach (\App\Models\Trip::STATUS as $val => $label)
                    <option value="{{ $val }}">{{ $label }}</option>
                @endforeach
            </select>

            <select wire:model.live="typeFilter" aria-label="Filter tipe trip" class="{{ $select }}">
                <option value="">Semua tipe</option>
                @foreach (\App\Support\Options::TRIP_TYPES as $val => $label)
                    <option value="{{ $val }}">{{ $label }}</option>
                @endforeach
            </select>

            <select wire:model.live="monthFilter" aria-label="Filter bulan" class="{{ $select }}">
                <option value="">Semua bulan</option>
                @foreach ($months as $m)
                    <option value="{{ $m }}">{{ \Carbon\Carbon::createFromFormat('Y-m-d', $m.'-01')->locale('id')->translatedFormat('F Y') }}</option>
                @endforeach
            </select>

            <button type="button" wire:click="resetFilters"
                    class="inline-flex items-center gap-1.5 rounded-xl bg-indigo-50 px-3.5 py-2.5 text-sm font-semibold text-indigo-700 transition-colors hover:bg-indigo-100 focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500">
                <x-icon name="refresh" class="h-4 w-4" />
                Reset filter
            </button>
        </div>

        {{-- Tabel --}}
        <div class="overflow-hidden rounded-2xl bg-white shadow-[0_8px_30px_-14px_rgba(79,70,229,0.18)] ring-1 ring-slate-100">
            <div class="overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead>
                        <tr class="border-b border-slate-100">
                            <th class="{{ $th }} whitespace-nowrap text-left">Nama Trip</th>
                            <th class="{{ $th }} whitespace-nowrap text-left">Nama Customer</th>
                            <th class="{{ $th }} whitespace-nowrap text-left">Tipe</th>
                            <th class="{{ $th }} text-left">Tanggal mulai - selesai</th>
                            <th class="{{ $th }} whitespace-nowrap text-left">Pax</th>
                            @if ($canPrice)
                                <th class="{{ $th }} whitespace-nowrap text-right">Harga</th>
                            @endif
                            <th class="{{ $th }} whitespace-nowrap text-left">Status</th>
                            <th class="{{ $th }} whitespace-nowrap text-right">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse ($trips as $t)
                            @php
                                [$icon, $tone] = $typeStyle[$t->trip_type] ?? ['map', 'bg-slate-100 text-slate-600'];
                                // LOGIKA HARGA BARU: Prioritaskan Final, lalu Rekomendasi
                                $displayPrice = $t->pricing?->final_price ?? $t->pricing?->recommended_price;
                                
                                // Tentukan class badge status berdasarkan mapping yang tepat
                                $statusClass = $statusStyles[$t->status] ?? 'bg-slate-100 text-slate-700 ring-slate-200';
                            @endphp
                            <tr wire:key="trip-{{ $t->id }}" class="transition-colors hover:bg-slate-50/70">
                                <td class="min-w-[12rem] px-3 py-4">
                                    <div class="flex items-center gap-3">
                                        <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl {{ $tone }}">
                                            <x-icon :name="$icon" class="h-5 w-5" />
                                        </span>
                                        <div class="min-w-0">
                                            <div class="font-semibold text-slate-900">{{ $t->trip_name }}</div>
                                            <div class="mt-0.5 text-xs">
                                                <a href="{{ route('trips.show', $t->id) }}" wire:navigate class="font-medium text-indigo-600 hover:text-indigo-800 hover:underline">{{ $t->trip_code }}</a>
                                            </div>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-3 py-4 text-slate-700">{{ $t->customer_name }}</td>
                                <td class="px-3 py-4">
                                    <span class="inline-block max-w-[9rem] rounded-full bg-slate-100 px-2.5 py-1 text-xs font-medium leading-snug text-slate-600">{{ \App\Support\Options::TRIP_TYPES[$t->trip_type] ?? $t->trip_type }}</span>
                                </td>
                                <td class="px-3 py-4 tabular-nums text-slate-600">
                                    <span class="inline-flex items-start gap-2">
                                        <x-icon name="calendar" class="mt-0.5 h-4 w-4 shrink-0 text-slate-400" />
                                        <span class="2xl:whitespace-nowrap">
                                            <span class="whitespace-nowrap">{{ $t->start_date->format('d/m/Y') }}</span>
                                            -
                                            <span class="whitespace-nowrap">{{ $t->end_date->format('d/m/Y') }}</span>
                                        </span>
                                    </span>
                                </td>
                                <td class="whitespace-nowrap px-3 py-4">
                                    <span class="inline-flex items-center gap-1.5 rounded-full bg-slate-50 px-2.5 py-1 text-xs font-semibold tabular-nums text-slate-700 ring-1 ring-slate-100">
                                        <x-icon name="users" class="h-3.5 w-3.5 text-slate-400" />
                                        {{ $t->pax }} pax
                                    </span>
                                </td>
                                @if ($canPrice)
                                    <td class="whitespace-nowrap px-3 py-4 text-right font-semibold tabular-nums text-slate-900">
                                        {{ $displayPrice !== null ? \App\Support\Money::rp($displayPrice) : 'Belum diset' }}
                                    </td>
                                @endif
                                <td class="whitespace-nowrap px-3 py-4">
                                    <!-- BADGE STATUS BARU (Menggunakan Mapping Warna) -->
                                    <span class="inline-flex items-center gap-1.5 rounded-full px-2.5 py-1 text-xs font-semibold ring-1 ring-inset {{ $statusClass }}">
                                        <span class="h-1.5 w-1.5 rounded-full bg-current opacity-80" aria-hidden="true"></span>
                                        {{ \App\Models\Trip::STATUS[$t->status] ?? $t->status }}
                                    </span>
                                </td>
                                <td class="whitespace-nowrap px-3 py-4">
                                    <div class="flex items-center justify-end gap-2">
                                        {{-- Tombol Edit (Mengarah ke halaman Detail) --}}
                                        <a href="{{ route('trips.show', $t->id) }}" wire:navigate
                                           class="inline-flex items-center gap-1.5 rounded-lg bg-indigo-50 px-2.5 py-1.5 text-xs font-semibold text-indigo-700 transition-colors hover:bg-indigo-100 focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500">
                                            <x-icon name="pencil" class="h-3.5 w-3.5" />
                                            Edit
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="8" class="px-3 py-12 text-center text-slate-500">Belum ada trip.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>