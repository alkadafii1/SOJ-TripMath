<div class="min-h-screen bg-[#f8fafc] p-4 sm:p-6 lg:p-8 font-sans text-slate-800">
    <div class="max-w-7xl mx-auto space-y-6">
        
        <!-- Header Dashboard -->
        <header class="flex flex-wrap items-center justify-between gap-4 bg-white p-4 rounded-2xl shadow-sm ring-1 ring-slate-100">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-xl bg-indigo-600 text-white flex items-center justify-center shadow-indigo-200 shadow-lg">
                    <x-icon name="map" class="w-5 h-5" />
                </div>
                <div>
                    <h1 class="text-lg font-bold text-slate-900 leading-tight">SOJ Trip Manager</h1>
                    <p class="text-xs text-slate-500 font-medium">Selamat datang, {{ auth()->user()->name ?? 'Owner SOJ' }}</p>
                </div>
            </div>
            
            <div class="flex items-center gap-2 sm:gap-3">
                <div class="hidden sm:flex items-center gap-2 text-xs font-medium text-slate-600 bg-slate-50 px-3 py-1.5 rounded-lg border border-slate-200">
                    <x-icon name="calendar" class="w-4 h-4 text-slate-400" />
                    {{ now()->translatedFormat('d M Y') }}
                </div>
                <button class="relative p-2 text-slate-400 hover:text-indigo-600 bg-slate-50 hover:bg-indigo-50 rounded-lg border border-slate-200 transition-colors">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"></path></svg>
                    <span class="absolute top-1.5 right-1.5 w-2 h-2 bg-red-500 rounded-full border-2 border-white"></span>
                </button>
                <div class="flex items-center gap-2 pl-2 sm:pl-3 border-l border-slate-200">
                    <div class="w-8 h-8 rounded-full bg-indigo-100 flex items-center justify-center text-indigo-700 font-bold text-sm ring-2 ring-white shadow-sm">
                        {{ substr(auth()->user()->name ?? 'O', 0, 1) }}
                    </div>
                    <span class="text-sm font-semibold text-slate-700 hidden sm:block">{{ auth()->user()->name ?? 'Owner SOJ' }}</span>
                </div>
            </div>
        </header>

        <!-- Title & Filter Periode -->
        <div class="flex flex-col sm:flex-row sm:items-end justify-between gap-4">
            <div>
                <h2 class="text-2xl font-bold tracking-tight text-slate-900">Ringkasan Dashboard</h2>
                <p class="mt-1 text-sm text-slate-500">Kondisi trip, jadwal keberangkatan, dan hasil bisnis dalam satu tempat.</p>
            </div>
            
            <div class="flex flex-wrap items-center gap-3 bg-white p-2 rounded-xl shadow-sm ring-1 ring-slate-100">
                <label class="text-xs font-semibold text-slate-500 uppercase tracking-wider pl-2">Periode</label>
                <select wire:model.live="preset" class="block w-full rounded-lg border-0 bg-slate-50 py-1.5 pl-3 pr-8 text-sm font-medium text-slate-700 focus:ring-2 focus:ring-indigo-500 sm:text-sm">
                    @foreach ($presets as $key => $label)
                        <option value="{{ $key }}">{{ $label }}</option>
                    @endforeach
                </select>
                
                @if ($preset === 'custom')
                    <label class="text-xs text-gray-500">Dari
                        <input aria-label="Tanggal mulai operasional" type="date" wire:model.blur="from" class="mt-1 block rounded-lg border-gray-300 text-sm">
                    </label>
                    <label class="text-xs text-gray-500">Sampai
                        <input aria-label="Tanggal akhir operasional" type="date" wire:model.blur="to" class="mt-1 block rounded-lg border-gray-300 text-sm">
                    </label>
                @endif
            </div>
        </div>

        <div class="h-4">
            <span wire:loading.delay role="status" class="text-xs text-gray-500">Memperbarui dashboard...</span>
        </div>
        
        @if ($error)
            <p role="alert" class="rounded-lg bg-amber-50 p-3 text-sm text-amber-800">{{ $error }}</p>
        @endif

        <div class="flex items-center gap-2 text-xs text-slate-500 bg-slate-100/50 p-3 rounded-lg">
            <svg class="w-4 h-4 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
            Ringkasan dan sebaran status berdasarkan tanggal mulai trip: <strong class="text-slate-700">{{ $period->label() }}</strong>. Hanya data yang boleh Anda lihat.
        </div>

        @php
            $cards = [
                ['Total Trip', $stats['total'], $stats['cancelled'].' trip dibatalkan', [], 'bg-slate-100 text-slate-600', 'map'],
                ['Dalam Persiapan', $stats['preparation'], 'Draft, Estimated, Negotiation', ['draft', 'estimated', 'negotiation'], 'bg-amber-50 text-amber-600', 'pencil'],
                ['Siap Berangkat', $stats['ready'], 'Confirmed, Deposit Paid, Paid', ['confirmed', 'deposit_paid', 'paid'], 'bg-emerald-50 text-emerald-600', 'check-circle'],
                ['Sedang Berjalan', $stats['ongoing'], 'Trip berstatus Ongoing', ['ongoing'], 'bg-indigo-50 text-indigo-600', 'play'],
                ['Trip Selesai', $stats['finished'], $stats['completed'].' completed / '.$stats['reconciled'].' reconciled', ['completed', 'reconciled'], 'bg-blue-50 text-blue-600', 'box'],
            ];
        @endphp

        <!-- Kotak Ringkasan (KPI Cards) -->
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-5">
            @foreach ($cards as [$label, $value, $description, $statuses, $tone, $icon])
                <a href="{{ route('trips.index', ['statuses' => implode(',', $statuses), 'startFrom' => $period->from->toDateString(), 'startTo' => $period->to->toDateString()]) }}" wire:navigate class="group relative overflow-hidden rounded-2xl border border-slate-200 bg-white p-5 shadow-sm transition-all duration-200 hover:-translate-y-1 hover:border-indigo-300 hover:shadow-md focus:outline-none focus:ring-2 focus:ring-indigo-500">
                    <div class="flex items-start justify-between mb-4">
                        <p class="text-xs font-bold uppercase tracking-wider text-slate-500">{{ $label }}</p>
                        <span class="flex h-8 w-8 items-center justify-center rounded-xl {{ $tone }} transition-transform group-hover:scale-110">
                            <x-icon name="{{ $icon }}" class="w-4 h-4" />
                        </span>
                    </div>
                    <div>
                        <p class="text-3xl font-extrabold tracking-tight text-slate-900">{{ $value }}</p>
                        <p class="mt-2 text-[11px] font-medium leading-relaxed text-slate-400">{{ $description }}</p>
                    </div>
                </a>
            @endforeach
        </div>

        <!-- Main Content (Full Width) -->
        <div class="space-y-6">
            
            <!-- Jadwal Trip -->
            <section class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
                <div class="flex flex-wrap items-center justify-between gap-3 p-5 border-b border-slate-100">
                    <div class="flex items-center gap-3">
                        <div class="p-2 bg-indigo-50 text-indigo-600 rounded-xl">
                            <x-icon name="calendar" class="w-5 h-5" />
                        </div>
                        <div>
                            <h2 class="text-base font-bold text-slate-900">Jadwal Trip Terdekat</h2>
                            <p class="mt-0.5 text-xs text-slate-500">Tidak mengikuti filter periode. Maksimal 10 trip per tab.</p>
                        </div>
                    </div>
                    <div class="flex flex-wrap gap-1 rounded-lg bg-slate-100/80 p-1" role="tablist" aria-label="Jadwal trip">
                        <button type="button" role="tab" aria-selected="{{ $tab === 'ongoing' ? 'true' : 'false' }}" wire:click="$set('tab', 'ongoing')" class="rounded-md px-3 py-1.5 text-xs font-semibold transition-all {{ $tab === 'ongoing' ? 'bg-white text-indigo-700 shadow-sm ring-1 ring-slate-200' : 'text-slate-600 hover:text-slate-900' }}">Sedang Berjalan</button>
                        <button type="button" role="tab" aria-selected="{{ $tab === 'upcoming' ? 'true' : 'false' }}" wire:click="$set('tab', 'upcoming')" class="rounded-md px-3 py-1.5 text-xs font-semibold transition-all {{ $tab === 'upcoming' ? 'bg-white text-indigo-700 shadow-sm ring-1 ring-slate-200' : 'text-slate-600 hover:text-slate-900' }}">Keberangkatan 14 Hari</button>
                    </div>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full min-w-[680px] text-left text-sm">
                        <thead class="border-b border-slate-100 bg-slate-50/50 text-xs text-slate-500">
                            <tr>
                                <th class="px-5 py-3 font-semibold">Trip / Customer</th>
                                <th class="px-4 py-3 font-semibold">Tanggal</th>
                                <th class="px-4 py-3 font-semibold">Pax</th>
                                <th class="px-4 py-3 font-semibold">Staff / Partner</th>
                                <th class="px-4 py-3 font-semibold">Status</th>
                                <th class="px-5 py-3 text-right font-semibold">Aksi</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            @forelse ($schedule as $trip)
                                <tr wire:key="schedule-{{ $trip->id }}" class="hover:bg-slate-50/60 transition-colors">
                                    <td class="px-5 py-4">
                                        <p class="font-semibold text-slate-900">{{ $trip->trip_name }}</p>
                                        <p class="mt-0.5 text-xs text-slate-500">{{ $trip->trip_code }} / {{ $trip->customer_name }}</p>
                                    </td>
                                    <td class="px-4 py-4 text-xs text-slate-600">
                                        <span class="font-medium text-slate-900">{{ $trip->start_date->format('d M Y') }}</span><br>
                                        <span class="text-slate-400">s/d {{ $trip->end_date->format('d M Y') }}</span>
                                    </td>
                                    <td class="px-4 py-4 font-medium text-slate-800">{{ $trip->effectivePax() }} org</td>
                                    <td class="px-4 py-4 text-xs text-slate-600">
                                        <span class="font-medium text-slate-900">{{ $trip->staff?->name ?? 'Belum ada staff' }}</span><br>
                                        <span class="text-slate-400">{{ $trip->partner?->name ?? 'Belum ada partner' }}</span>
                                    </td>
                                    <td class="px-4 py-4">
                                        <span class="inline-flex items-center rounded-full px-2.5 py-1 text-[11px] font-semibold ring-1 ring-inset {{ $trip->statusBadgeClass() }}">
                                            {{ \App\Models\Trip::STATUS[$trip->status] }}
                                        </span>
                                    </td>
                                    <td class="px-5 py-4 text-right">
                                        <a href="{{ route('trips.show', $trip) }}" wire:navigate class="inline-flex items-center gap-1.5 rounded-lg bg-indigo-50 px-3 py-1.5 text-xs font-semibold text-indigo-700 hover:bg-indigo-100 transition-colors">
                                            <x-icon name="eye" class="w-3.5 h-3.5" /> Detail
                                        </a>
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="6" class="px-5 py-10 text-center text-slate-500">{{ $tab === 'ongoing' ? 'Belum ada trip yang sedang berjalan.' : 'Belum ada keberangkatan dalam 14 hari ke depan.' }}</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </section>

            <!-- Sebaran Status Trip -->
            <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                <div class="flex items-center gap-3 mb-5">
                    <div class="p-2 bg-indigo-50 text-indigo-600 rounded-xl">
                        <x-icon name="chart-pie" class="w-5 h-5" />
                    </div>
                    <div>
                        <h2 class="text-base font-bold text-slate-900">Sebaran Status Trip</h2>
                        <p class="mt-0.5 text-xs text-slate-500">Jumlah trip per status asli dalam periode operasional.</p>
                    </div>
                </div>
                
                @php 
                    $max = max(1, max(array_column($stats['statuses'], 'count'))); 
                    $statusesChunked = collect($stats['statuses'])->split(2);

                    $statusIcons = [
                        'draft' => 'pencil',
                        'estimated' => 'tag',
                        'negotiation' => 'users',
                        'confirmed' => 'check-circle',
                        'deposit_paid' => 'clock',
                        'paid' => 'check-circle',
                        'ongoing' => 'play',
                        'completed' => 'box',
                        'reconciled' => 'database',
                        'cancelled' => 'close',
                    ];
                @endphp
                
                <div class="grid gap-x-12 gap-y-3 md:grid-cols-2">
                    @foreach ($statusesChunked as $chunk)
                        <div class="space-y-3">
                            @foreach ($chunk as $row)
                                <div class="grid grid-cols-[130px_1fr_30px] items-center gap-4 text-xs">
                                    <div class="flex items-center gap-2 text-slate-600 font-semibold truncate">
                                        <x-icon :name="$statusIcons[$row['status']] ?? 'tag'" class="w-4 h-4 text-slate-500 shrink-0" />
                                        <span>{{ $row['label'] }}</span>
                                    </div>
                                    <div class="h-2 overflow-hidden rounded-full bg-slate-100">
                                        <div class="h-full rounded-full {{ $row['status'] === 'cancelled' ? 'bg-slate-300' : 'bg-indigo-500' }}" style="width: {{ $row['count'] / $max * 100 }}%"></div>
                                    </div>
                                    <span class="text-right font-bold text-slate-900 bg-slate-50 py-0.5 rounded">{{ $row['count'] }}</span>
                                </div>
                            @endforeach
                        </div>
                    @endforeach
                </div>
            </section>

            <!-- Ringkasan Keuangan (Desain Baru Gaya Gambar 1) -->
            @can('view-finance')
                <section class="space-y-4">
                    <!-- Header Section -->
                    <div class="flex items-center justify-between">
                        <div>
                            <h2 class="text-lg font-bold text-slate-900">Ringkasan keuangan</h2>
                            <p class="mt-0.5 text-xs text-slate-500">Periode {{ $period->label() }}. Dihitung dari trip yang selesai pada periode itu dan sudah direkonsiliasi.</p>
                        </div>
                    </div>

                    <!-- Panggil Komponen Livewire (Data Tetap) -->
                    <div class="[&>div>div]:grid [&>div>div]:grid-cols-1 [&>div>div]:lg:grid-cols-3 [&>div>div]:gap-4
                                [&>div>div>div]:bg-white [&>div>div>div]:rounded-2xl [&>div>div>div]:border [&>div>div>div]:border-slate-200 [&>div>div>div]:p-5 [&>div>div>div]:shadow-sm">
                        <livewire:dashboard.summary />
                    </div>
                </section>
            @endcan
        </div>

        <!-- Perlu Perhatian -->
        @if ($actions)
            <section class="rounded-2xl border border-amber-200 bg-amber-50/50 p-5 shadow-sm">
                <div class="flex items-center gap-2 mb-2">
                    <svg class="w-5 h-5 text-amber-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path></svg>
                    <h2 class="text-sm font-bold text-amber-900">Perlu perhatian</h2>
                </div>
                <p class="text-xs text-amber-800 mb-4">Pemeriksaan saat ini, tidak mengikuti periode. Satu trip bisa masuk beberapa kategori.</p>
                
                <div class="flex flex-col gap-2">
                    @foreach ($actions as $action)
                        <details class="group bg-white rounded-xl border border-amber-200 p-4 text-sm shadow-sm [&_summary::-webkit-details-marker]:hidden">
                            <summary class="flex items-center justify-between cursor-pointer font-medium text-slate-800 hover:text-amber-900 transition-colors">
                                <span class="flex items-center gap-2">
                                    <svg class="w-4 h-4 text-amber-500 transition-transform group-open:rotate-90 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path></svg>
                                    {{ $action['count'] }} trip: {{ $action['title'] }}
                                </span>
                            </summary>
                            <ul class="mt-3 space-y-2 pl-7">
                                @foreach ($action['trips'] as $trip)
                                    <li>
                                        <a href="{{ $trip['url'] }}" wire:navigate class="flex items-center gap-2 text-xs text-indigo-600 hover:text-indigo-800 hover:underline">
                                            <span class="w-1.5 h-1.5 rounded-full bg-indigo-400 shrink-0"></span>
                                            <span class="truncate">{{ $trip['code'] }} / {{ $trip['name'] }}</span>
                                        </a>
                                    </li>
                                @endforeach
                                @if ($action['count'] > count($action['trips']))
                                    <li class="text-[11px] text-amber-700 pt-2 font-medium">Menampilkan {{ count($action['trips']) }} dari {{ $action['count'] }} trip.</li>
                                @endif
                            </ul>
                        </details>
                    @endforeach
                </div>
            </section>
        @endif

    </div>
</div>