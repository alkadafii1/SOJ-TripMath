@php
    $fmt = fn ($v) => rtrim(rtrim(number_format((float) $v, 4, '.', ''), '0'), '.');
    $unitTypes = \App\Services\TripItemFactory::UNIT_TYPES;
    $durationTypes = \App\Services\TripItemFactory::DURATION_TYPES;
    $qtyLabels = [
        'per_room' => 'Jumlah kamar',
        'per_vehicle' => 'Jumlah kendaraan',
        'per_day' => 'Jumlah (orang/unit)',
        'per_night' => 'Jumlah (unit)',
        'per_trip' => 'Jumlah (unit)',
    ];
    $paxNow = $trip->effectivePax();
@endphp

<div class="min-h-screen bg-[#f8f9fc] pb-12 font-sans text-slate-800">
    
    <!-- Top Bar (Breadcrumb & Quick Actions) -->
    <div class="bg-white border-b border-slate-200 px-4 sm:px-6 lg:px-8 py-3 flex items-center justify-between sticky top-0 z-40">
        <div class="flex items-center gap-2 text-sm text-slate-500">
            <a href="{{ route('trips.index') }}" wire:navigate class="hover:text-indigo-600 transition-colors">Semua Trip</a>
            <span>/</span>
            <span class="font-medium text-slate-900">{{ $trip->trip_name }}</span>
        </div>
        <div class="flex items-center gap-3">
            @can('view-costs')
                <a href="{{ route('trips.pricing', $trip->id) }}" wire:navigate class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-medium rounded-lg border border-slate-300 text-slate-700 hover:bg-slate-50 transition-colors">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                    Harga & Negosiasi
                </a>
            @endcan
        </div>
    </div>

    <!-- BLOK KODE ASLI DARI TEMANMU (DIKEMBALIKAN) -->
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6 pt-6">

        <!-- 1. Header Judul Trip -->
        <div>
            <h1 class="text-[32px] leading-tight font-bold text-slate-900 tracking-tight">{{ $trip->trip_name }}</h1>
            <div class="flex items-center gap-2 mt-1.5 text-sm text-slate-500">
                <span class="font-medium text-slate-700">{{ $trip->trip_code }}</span>
                <span>&middot;</span>
                <span>{{ \App\Support\Options::TRIP_TYPES[$trip->trip_type] ?? $trip->trip_type }}</span>
            </div>
        </div>

        <!-- Flash Messages -->
        @if (session('status'))
            <div class="rounded-xl bg-green-50 border border-green-200 p-4 text-sm text-green-800 flex items-center gap-3">
                <svg class="w-5 h-5 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                {{ session('status') }}
            </div>
        @endif
        @if (session('warning'))
            <div class="rounded-xl bg-amber-50 border border-amber-200 p-4 text-sm text-amber-900 flex items-center gap-3">
                <svg class="w-5 h-5 text-amber-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path></svg>
                {{ session('warning') }}
            </div>
        @endif
        @if (session('error'))
            <div class="rounded-xl bg-red-50 border border-red-200 p-4 text-sm text-red-800 flex items-center gap-3">
                <svg class="w-5 h-5 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                {{ session('error') }}
            </div>
        @endif

        <!-- 2. Overview Bar (4 Kotak Ringkasan) -->
        <div class="bg-[#f3f4f6]/70 rounded-2xl p-2 flex flex-col md:flex-row items-stretch justify-between divide-y md:divide-y-0 md:divide-x divide-slate-200/60 border border-slate-200/50">
            
            <!-- Info 1: Tanggal -->
            <div class="flex items-center gap-3 px-4 py-3 flex-1">
                <div class="w-8 h-8 rounded-full bg-indigo-100/80 flex items-center justify-center text-indigo-600 shrink-0">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg>
                </div>
                <div>
                    <p class="text-[10px] font-semibold text-slate-500 uppercase tracking-wider mb-0.5">Jadwal Keberangkatan</p>
                    <p class="text-sm font-bold text-slate-900">{{ $trip->start_date->format('d M Y') }} - {{ $trip->end_date->format('d M Y') }} ({{ $trip->duration_days }}H{{ $trip->nights }}M)</p>
                </div>
            </div>

            <!-- Info 2: Kapasitas -->
            <div class="flex items-center gap-3 px-4 py-3 flex-1">
                <div class="w-8 h-8 rounded-full bg-fuchsia-100/80 flex items-center justify-center text-fuchsia-600 shrink-0">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"></path></svg>
                </div>
                <div>
                    <p class="text-[10px] font-semibold text-slate-500 uppercase tracking-wider mb-0.5">Kapasitas Rombongan</p>
                    <p class="text-sm font-bold text-slate-900">
                        {{ $trip->pax }} Orang 
                        <span class="font-normal text-slate-500">({{ \App\Support\Options::TRIP_TYPES[$trip->trip_type] ?? $trip->trip_type }})</span>
                        @if ($paxNow !== $trip->pax)
                            <span class="text-xs font-normal text-amber-600 ml-1">(Biaya: {{ $paxNow }} org)</span>
                        @endif
                    </p>
                </div>
            </div>

            <!-- Info 3: PIC -->
            <div class="flex items-center gap-3 px-4 py-3 flex-1">
                <div class="w-8 h-8 rounded-full bg-cyan-100/80 flex items-center justify-center text-cyan-600 shrink-0">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path></svg>
                </div>
                <div>
                    <p class="text-[10px] font-semibold text-slate-500 uppercase tracking-wider mb-0.5">Penanggung Jawab</p>
                    <p class="text-sm font-bold text-slate-900">{{ $trip->staff?->name ?? '-' }}</p>
                </div>
            </div>

            <!-- Info 4: Status / Kontak -->
            <div class="flex items-center gap-3 px-4 py-3 flex-1">
                <div class="w-8 h-8 rounded-full bg-emerald-100/80 flex items-center justify-center text-emerald-600 shrink-0">
                    @if($trip->status === 'draft')
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path></svg>
                    @else
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"></path></svg>
                    @endif
                </div>
                <div>
                    @if($trip->status === 'draft')
                        <p class="text-[10px] font-semibold text-slate-500 uppercase tracking-wider mb-0.5">Status Kontrak</p>
                        <p class="text-sm font-bold text-slate-900">{{ \App\Models\Trip::STATUS[$trip->status] ?? $trip->status }} Siap Hitung HPP</p>
                    @else
                        <p class="text-[10px] font-semibold text-slate-500 uppercase tracking-wider mb-0.5">Kontak Customer</p>
                        <p class="text-sm font-bold text-slate-900">{{ $trip->customer_name }} <span class="font-normal text-slate-500">({{ $trip->customer_contact ?? '-' }})</span></p>
                    @endif
                </div>
            </div>

        </div>

        <!-- 3. Konten Utama -->
        <div class="space-y-6">
            
            <!-- Card Informasi Perjalanan & Catatan -->
            <div class="bg-white rounded-2xl shadow-sm border border-slate-200 overflow-hidden">
                <div class="px-6 py-5 border-b border-slate-100 flex items-center gap-3">
                    <div class="flex-shrink-0 w-8 h-8 rounded-lg bg-indigo-50 flex items-center justify-center text-indigo-600">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                    </div>
                    <h3 class="text-base font-semibold text-slate-900">Informasi Perjalanan & Catatan Khusus</h3>
                </div>
                
                <div class="p-6 space-y-4 text-sm">
                    <!-- Detail Destinasi & Partner -->
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <span class="text-slate-500 block text-xs mb-1">Destinasi</span>
                            <span class="font-medium text-slate-900">{{ $destinations ? implode(', ', $destinations) : '-' }}</span>
                        </div>
                        <div>
                            <span class="text-slate-500 block text-xs mb-1">Operational Partner</span>
                            <span class="font-medium text-slate-900">{{ $trip->partner?->name ?? '-' }}</span>
                        </div>
                        <div>
                            <span class="text-slate-500 block text-xs mb-1">Customer</span>
                            <span class="font-medium text-slate-900">{{ $trip->customer_name }}</span>
                        </div>
                        <div>
                            <span class="text-slate-500 block text-xs mb-1">Kontak Customer</span>
                            <span class="font-medium text-slate-900">{{ $trip->customer_contact ?: '-' }}</span>
                        </div>
                        <div>
                            <span class="text-slate-500 block text-xs mb-1">Durasi</span>
                            <span class="font-medium text-slate-900">{{ $trip->duration_days }} hari / {{ $trip->nights }} malam</span>
                        </div>
                        @if ($trip->trip_type === 'open_trip')
                            <div>
                                <span class="text-slate-500 block text-xs mb-1">Open Trip Info</span>
                                <span class="font-medium text-slate-900">Min: {{ $trip->min_pax ?? '-' }} / Maks: {{ $trip->max_capacity ?? '-' }} / Confirmed: {{ $trip->confirmed_pax ?? '-' }}</span>
                            </div>
                        @endif
                    </div>
                    
                    @if ($trip->notes)
                        <div class="pt-4 border-t border-slate-100">
                            <span class="text-slate-500 block text-xs mb-1">Catatan Internal:</span>
                            <p class="text-slate-700">{{ $trip->notes }}</p>
                        </div>
                    @endif
                    @if ($trip->status === 'cancelled')
                        <div class="p-3 bg-red-50 rounded-lg border border-red-100 mt-2">
                            <p class="text-sm text-red-800"><span class="font-medium">Alasan batal:</span> {{ $trip->cancel_reason }}</p>
                        </div>
                    @endif

                    <!-- Aksi Cepat -->
                    <div class="pt-8 mt-4 border-t border-slate-200">
                        <div class="flex items-center justify-between mb-4">
                            <h3 class="text-lg font-bold text-slate-900">Aksi Cepat</h3>
                            <span class="text-sm font-medium text-slate-500">Tahap Draft</span>
                        </div>
                        
                        <div class="flex flex-col gap-3">
                            @if ($editable)
                                <a href="{{ route('trips.edit', $trip->id) }}" wire:navigate
                                   class="w-full flex items-center justify-center gap-2 px-5 py-3 text-[15px] font-semibold rounded-full bg-[#5038ED] text-white hover:bg-[#4330c6] transition-colors shadow-sm">
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path></svg>
                                    Edit Perjalanan
                                </a>
                            @endif

                            @can('view-costs')
                                @if (in_array($trip->status, ['ongoing', 'completed', 'reconciled'], true))
                                    <a href="{{ route('trips.reconcile', $trip->id) }}" wire:navigate
                                       class="w-full flex items-center justify-center gap-2 px-5 py-3 text-[15px] font-semibold rounded-full border border-indigo-200 text-indigo-700 bg-indigo-50 hover:bg-indigo-100 transition-colors mb-2">
                                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 7h6m0 10v-3m-3 3h.01M9 17h.01M9 14h.01M12 14h.01M15 11h.01M12 11h.01M9 11h.01M7 21h10a2 2 0 002-2V5a2 2 0 00-2-2H7a2 2 0 00-2 2v14a2 2 0 002 2z"></path></svg>
                                        Biaya Aktual & Rekonsiliasi
                                    </a>
                                @endif
                            @endcan

                            @can('view-finance')
                                @if (in_array($trip->status, ['confirmed', 'deposit_paid', 'paid', 'ongoing', 'completed', 'reconciled'], true))
                                    <a href="{{ route('trips.payments', $trip->id) }}" wire:navigate
                                       class="w-full flex items-center justify-center gap-2 px-5 py-3 text-[15px] font-semibold rounded-full border border-slate-300 text-slate-700 hover:bg-slate-50 transition-colors bg-white">
                                        <svg class="w-5 h-5 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z"></path></svg>
                                        Pembayaran
                                    </a>
                                @endif
                            @endcan

                            @if ($editable)
                                @if ($trip->status === 'draft')
                                    <button wire:click="changeStatus('estimated')"
                                            class="w-full flex items-center justify-center gap-2 px-5 py-3 text-[15px] font-semibold rounded-full bg-blue-600 text-white hover:bg-blue-700 transition-colors shadow-sm">
                                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 7h6m0 10v-3m-3 3h.01M9 17h.01M9 14h.01M12 14h.01M15 11h.01M12 11h.01M9 11h.01M7 21h10a2 2 0 002-2V5a2 2 0 00-2-2H7a2 2 0 00-2 2v14a2 2 0 002 2z"></path></svg>
                                        Estimasi
                                    </button>
                                @endif
                                @if ($trip->status === 'estimated')
                                    <button wire:click="changeStatus('draft')"
                                            class="w-full flex items-center justify-center gap-2 px-5 py-3 text-[15px] font-semibold rounded-full border border-slate-300 text-slate-700 hover:bg-slate-50 transition-colors bg-white">
                                        Kembali ke Draft
                                    </button>
                                @endif
                            @endif

                            @if ($canAdvance && in_array($trip->status, ['deposit_paid', 'paid'], true))
                                <button wire:click="changeStatus('ongoing')" wire:confirm="Tandai trip sedang berjalan (Ongoing)?"
                                        class="w-full flex items-center justify-center gap-2 px-5 py-3 text-[15px] font-semibold rounded-full bg-blue-600 text-white hover:bg-blue-700 transition-colors shadow-sm">
                                    Mulai Trip
                                </button>
                            @endif
                            
                            @if ($canAdvance && $trip->status === 'ongoing')
                                <button wire:click="changeStatus('completed')" wire:confirm="Tandai trip selesai (Completed)? Setelah ini biaya aktual dilengkapi lalu direkonsiliasi."
                                        class="w-full flex items-center justify-center gap-2 px-5 py-3 text-[15px] font-semibold rounded-full bg-emerald-600 text-white hover:bg-emerald-700 transition-colors shadow-sm">
                                    Selesaikan Trip
                                </button>
                            @endif

                            @if ($canCancel)
                                <button wire:click="$set('showCancel', true)"
                                        class="w-full flex items-center justify-center gap-2 px-5 py-3 text-[15px] font-semibold rounded-full border border-red-200 text-red-600 hover:bg-red-50 transition-colors bg-white mt-2">
                                    Batalkan Trip
                                </button>
                            @endif
                        </div>
                    </div>

                </div>
            </div>

            <!-- Card Komponen Biaya -->
            <div class="bg-white rounded-2xl shadow-sm border border-slate-200 overflow-hidden">
                <div class="px-6 py-5 border-b border-slate-100 flex items-center gap-3">
                    <div class="flex-shrink-0 w-8 h-8 rounded-lg bg-emerald-50 flex items-center justify-center text-emerald-600">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 7h6m0 10v-3m-3 3h.01M9 17h.01M9 14h.01M12 14h.01M15 11h.01M12 11h.01M9 11h.01M7 21h10a2 2 0 002-2V5a2 2 0 00-2-2H7a2 2 0 00-2 2v14a2 2 0 002 2z"></path></svg>
                    </div>
                    <h3 class="text-base font-semibold text-slate-900">Komponen Biaya</h3>
                </div>

                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-slate-200 text-sm">
                        <thead class="bg-slate-50">
                            <tr>
                                <th class="px-6 py-3 text-left font-medium text-slate-600">Komponen</th>
                                <th class="px-6 py-3 text-left font-medium text-slate-600">Tipe</th>
                                <th class="px-6 py-3 text-left font-medium text-slate-600">Sifat</th>
                                <th class="px-6 py-3 text-left font-medium text-slate-600">Perhitungan</th>
                                @if ($showMoney)
                                    <th class="px-6 py-3 text-right font-medium text-slate-600">Subtotal</th>
                                @endif
                                <th class="px-6 py-3"></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            @forelse ($items->groupBy('category') as $category => $group)
                                <tr class="bg-slate-50/50">
                                    <td colspan="4" class="px-6 py-2.5 font-semibold text-slate-800 text-xs uppercase tracking-wider">{{ $category }}</td>
                                    @if ($showMoney)
                                        <td class="px-6 py-2.5 text-right font-bold text-slate-800">
                                            {{ \App\Support\Money::rp($summary['by_category'][$category] ?? 0) }}
                                        </td>
                                    @endif
                                    <td></td>
                                </tr>

                                @foreach ($group as $item)
                                    @php
                                        $line = $summary['lines'][$item->id] ?? null;
                                        $dupKey = $item->item_type.'#'.$item->item_id;
                                        $zeroDuration = in_array($item->cost_type, ['per_room', 'per_night'], true)
                                            && (float) ($item->duration_qty ?? 1) <= 0;
                                    @endphp
                                    <tr wire:key="item-{{ $item->id }}" class="hover:bg-slate-50/50 transition-colors">
                                        <td class="px-6 py-4">
                                            <div class="font-medium text-slate-900">{{ $item->item_name }}</div>
                                            <div class="flex flex-wrap gap-1 mt-1">
                                                @if ($item->is_additional)
                                                    <span class="rounded bg-sky-100 px-1.5 py-0.5 text-[10px] font-semibold text-sky-800">tambahan</span>
                                                @elseif (! $item->is_required)
                                                    <span class="text-[10px] text-slate-400">(opsional)</span>
                                                @endif
                                                @if (($dupes[$dupKey] ?? 0) > 1)
                                                    <span class="rounded bg-amber-100 px-1.5 py-0.5 text-[10px] font-semibold text-amber-800" title="Komponen yang sama muncul lebih dari satu kali di trip ini">{{ $dupes[$dupKey] }} baris</span>
                                                @endif
                                            </div>
                                            @if ($item->notes)
                                                <div class="text-xs text-slate-500 mt-1">{{ $item->notes }}</div>
                                            @endif
                                        </td>
                                        <td class="px-6 py-4 text-slate-700">{{ \App\Support\Options::COST_TYPES[$item->cost_type] ?? $item->cost_type }}</td>
                                        <td class="px-6 py-4 text-slate-700">{{ $item->cost_behavior === 'fixed' ? 'Fixed' : 'Variable' }}</td>
                                        <td class="px-6 py-4 text-slate-700">
                                            {{ \App\Support\CostExplain::forItem($item, $line, $paxNow, $showMoney) }}
                                            @if ($item->override_quantity !== null)
                                                <span class="block text-xs text-amber-600 mt-0.5" title="{{ $item->override_reason }}">
                                                    (manual, otomatis {{ $fmt($item->calculated_quantity) }})
                                                </span>
                                            @endif
                                            @if ($zeroDuration)
                                                <div class="text-xs text-amber-600 mt-0.5">0 malam, biaya Rp0. Cek jumlah malam di data trip.</div>
                                            @endif
                                        </td>
                                        @if ($showMoney)
                                            <td class="px-6 py-4 text-right font-medium text-slate-900">{{ \App\Support\Money::rp($line['amount'] ?? 0) }}</td>
                                        @endif
                                        <td class="px-6 py-4 text-right whitespace-nowrap space-x-3">
                                            @if ($editable)
                                                <button wire:click="startEdit({{ $item->id }})" class="text-indigo-600 hover:text-indigo-800 font-medium">Ubah</button>
                                                <button wire:click="removeItem({{ $item->id }})" wire:confirm="Hapus komponen ini dari trip?"
                                                        class="text-red-600 hover:text-red-800 font-medium">Hapus</button>
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            @empty
                                <tr><td colspan="6" class="px-6 py-10 text-center text-slate-500">Belum ada komponen biaya yang ditambahkan.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                {{-- Form tambah komponen --}}
                @if ($editable)
                    <div class="p-6 border-t border-slate-200 bg-slate-50/50">
                        <form wire:submit="addItem" class="space-y-4">
                            <h4 class="text-sm font-semibold text-slate-800">Tambah Komponen Biaya</h4>

                            <div class="grid grid-cols-1 sm:grid-cols-6 gap-4">
                                <div class="sm:col-span-2">
                                    <label class="block text-xs font-medium text-slate-600 mb-1.5">Jenis</label>
                                    <select wire:model.live="itemForm.item_type" class="w-full rounded-xl border-slate-300 bg-white text-sm focus:ring-indigo-500 py-2 px-3 shadow-sm">
                                        <option value="">-- pilih --</option>
                                        @foreach (\App\Support\Options::ITEM_TYPES as $val => $label)
                                            <option value="{{ $val }}">{{ $label }}</option>
                                        @endforeach
                                    </select>
                                    @error('itemForm.item_type')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                                </div>
                                <div class="sm:col-span-4">
                                    <label class="block text-xs font-medium text-slate-600 mb-1.5">Komponen</label>
                                    <select wire:model.live="itemForm.item_id" class="w-full rounded-xl border-slate-300 bg-white text-sm focus:ring-indigo-500 py-2 px-3 shadow-sm">
                                        <option value="">-- pilih --</option>
                                        @foreach ($choices as $id => $label)
                                            <option value="{{ $id }}">{{ $label }}</option>
                                        @endforeach
                                    </select>
                                    @error('itemForm.item_id')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                                </div>
                            </div>

                            @if ($formInfo)
                                @php
                                    $ft = $formInfo['type'];
                                @endphp

                                <div class="grid grid-cols-1 sm:grid-cols-6 gap-4">
                                    <div class="sm:col-span-2">
                                        <label class="block text-xs font-medium text-slate-600 mb-1.5">Tipe biaya</label>
                                        <select wire:model.live="itemForm.cost_type" class="w-full rounded-xl border-slate-300 bg-white text-sm focus:ring-indigo-500 py-2 px-3 shadow-sm">
                                            @foreach ($addTypeOptions as $val => $label)
                                                <option value="{{ $val }}">{{ $label }}</option>
                                            @endforeach
                                        </select>
                                        @error('itemForm.cost_type')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                                    </div>

                                    @if ($ft === 'per_person')
                                        <div class="sm:col-span-2">
                                            <label class="block text-xs font-medium text-slate-600 mb-1.5">Jumlah orang</label>
                                            <input type="number" min="1" max="{{ $paxNow }}" wire:model.live.debounce.400ms="itemForm.people_count"
                                                   placeholder="Semua ({{ $paxNow }})"
                                                   class="w-full rounded-xl border-slate-300 bg-white text-sm focus:ring-indigo-500 py-2 px-3 shadow-sm">
                                            <p class="mt-1 text-[11px] text-slate-500">Kosongkan bila semua peserta.</p>
                                            @error('itemForm.people_count')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                                        </div>
                                    @elseif ($ft === 'percentage')
                                        <div class="sm:col-span-4">
                                            <p class="mt-6 text-xs text-slate-500">Biaya persentase mengikuti pengaturan komponen di data master.</p>
                                        </div>
                                    @elseif ($formInfo['has_cap'])
                                        <div class="sm:col-span-2">
                                            <label class="block text-xs font-medium text-slate-600 mb-1.5">Jumlah unit</label>
                                            <p class="mt-1 text-sm text-slate-700">
                                                Otomatis: <span class="font-semibold text-slate-900">{{ $formInfo['auto_units'] }}</span>
                                                <span class="text-xs text-slate-500">(kap. {{ $formInfo['capacity'] }} org/unit)</span>
                                            </p>
                                            <p class="text-[11px] text-slate-500">Bisa diganti manual lewat tombol Ubah.</p>
                                        </div>
                                    @else
                                        <div class="sm:col-span-2">
                                            <label class="block text-xs font-medium text-slate-600 mb-1.5">{{ $qtyLabels[$ft] ?? 'Jumlah' }}</label>
                                            <input type="number" step="1" min="1" wire:model.live.debounce.400ms="itemForm.quantity"
                                                   class="w-full rounded-xl border-slate-300 bg-white text-sm focus:ring-indigo-500 py-2 px-3 shadow-sm">
                                            @error('itemForm.quantity')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                                        </div>
                                    @endif

                                    @if (in_array($ft, $durationTypes, true))
                                        <div class="sm:col-span-2">
                                            <label class="block text-xs font-medium text-slate-600 mb-1.5">
                                                {{ $ft === 'per_day' ? 'Jumlah hari' : 'Jumlah malam' }}
                                            </label>
                                            <input type="number" step="0.5" min="0.5" wire:model.live.debounce.400ms="itemForm.duration_qty"
                                                   class="w-full rounded-xl border-slate-300 bg-white text-sm focus:ring-indigo-500 py-2 px-3 shadow-sm">
                                            @error('itemForm.duration_qty')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                                        </div>
                                    @endif
                                </div>

                                @if ($showMoney && $formInfo['preview'])
                                    <div class="rounded-xl bg-indigo-50 border border-indigo-100 p-3 text-sm text-indigo-900 flex items-center justify-between">
                                        <span>Perkiraan: {{ $formInfo['preview']['text'] }}</span>
                                        <span class="font-bold text-indigo-700 text-base">{{ \App\Support\Money::rp($formInfo['preview']['amount']) }}</span>
                                    </div>
                                @endif

                                <div class="flex justify-end pt-2">
                                    <button type="submit" class="inline-flex items-center gap-2 px-4 py-2 text-sm font-medium rounded-xl bg-slate-900 text-white hover:bg-slate-800 transition-colors shadow-sm">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path></svg>
                                        Tambah Komponen
                                    </button>
                                </div>
                            @endif
                        </form>
                    </div>
                @endif
            </div>

            <!-- Card Ringkasan Biaya -->
            @if ($showMoney && $summary)
                <div class="bg-white rounded-2xl shadow-sm border border-slate-200 p-6 sm:p-8">
                    <div class="flex items-center justify-between mb-6">
                        <h3 class="text-base font-semibold text-slate-900">Ringkasan Biaya</h3>
                        <span class="text-xs font-medium text-slate-500 bg-slate-100 px-2.5 py-1 rounded-md">Standard Cost</span>
                    </div>
                    
                    <div class="rounded-xl bg-[#ffffff] border border-[#e5dcff] p-6 mb-6">
                        <dt class="text-[#000000] text-sm font-bold mb-1">Total Standard Cost</dt>
                        <dd class="text-4xl font-bold text-[#5038ED]">{{ \App\Support\Money::rp($summary['total']) }}</dd>
                    </div>
                    
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
                        <!-- Kotak Biaya per Orang -->
                        <div class="bg-white rounded-xl border border-slate-200 p-5 flex flex-col justify-between">
                            <div class="flex justify-between items-start">
                                <div>
                                    <p class="text-[10px] font-semibold text-slate-500 uppercase tracking-wider mb-1">BIAYA PER PESERTA</p>
                                    <p class="text-xl font-bold text-[#5038ED] leading-tight">{{ \App\Support\Money::rp($summary['cost_per_person']) }}</p>
                                </div>
                                <div class="w-8 h-8 rounded-full bg-indigo-50 flex items-center justify-center text-indigo-600 shrink-0">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path></svg>
                                </div>
                            </div>
                            <p class="text-[11px] text-slate-500 flex items-center gap-1.5 mt-4">
                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 7h6m0 10v-3m-3 3h.01M9 17h.01M9 14h.01M12 14h.01M15 11h.01M12 11h.01M9 11h.01M7 21h10a2 2 0 002-2V5a2 2 0 00-2-2H7a2 2 0 00-2 2v14a2 2 0 002 2z"></path></svg>
                                Dasar HPP per orang ({{ $paxNow }} pax)
                            </p>
                        </div>

                        <!-- Kotak Fixed Cost -->
                        <div class="bg-white rounded-xl border border-slate-200 p-5 flex flex-col justify-between">
                            <div class="flex justify-between items-start">
                                <div>
                                    <p class="text-[10px] font-semibold text-slate-500 uppercase tracking-wider mb-1">FIXED COST</p>
                                    <p class="text-xl font-bold text-slate-800 leading-tight">{{ \App\Support\Money::rp($summary['fixed_total']) }}</p>
                                </div>
                                <div class="w-8 h-8 rounded-full bg-slate-100 flex items-center justify-center text-slate-500 shrink-0">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 7h6m0 10v-3m-3 3h.01M9 17h.01M9 14h.01M12 14h.01M15 11h.01M12 11h.01M9 11h.01M7 21h10a2 2 0 002-2V5a2 2 0 00-2-2H7a2 2 0 00-2 2v14a2 2 0 002 2z"></path></svg>
                                </div>
                            </div>
                            <p class="text-[11px] text-slate-500 flex items-center gap-1.5 mt-4">
                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                                Total biaya tetap
                            </p>
                        </div>

                        <!-- Kotak Variable Cost -->
                        <div class="bg-white rounded-xl border border-slate-200 p-5 flex flex-col justify-between">
                            <div class="flex justify-between items-start">
                                <div>
                                    <p class="text-[10px] font-semibold text-slate-500 uppercase tracking-wider mb-1">VARIABLE COST</p>
                                    <p class="text-xl font-bold text-slate-800 leading-tight">{{ \App\Support\Money::rp($summary['variable_total']) }}</p>
                                </div>
                                <div class="w-8 h-8 rounded-full bg-slate-100 flex items-center justify-center text-slate-500 shrink-0">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"></path></svg>
                                </div>
                            </div>
                            <p class="text-[11px] text-slate-500 flex items-center gap-1.5 mt-4">
                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                                Total biaya variabel
                            </p>
                        </div>
                    </div>
                    
                    <p class="text-[11px] text-slate-500 leading-relaxed mb-6">
                        * Angka dihitung otomatis oleh server berdasarkan tarif yang tersimpan. Harga jual final diatur di halaman Harga & Negosiasi.
                    </p>

                    @can('view-costs')
                        <a href="{{ route('trips.pricing', $trip->id) }}" wire:navigate
                           class="w-full flex items-center justify-center gap-2 px-5 py-3.5 text-[15px] font-semibold rounded-full bg-[#5038ED] text-white hover:bg-[#4330c6] transition-colors shadow-sm">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                            Harga & Negosiasi
                        </a>
                    @endcan
                </div>
            @endif

        </div>
    </div>

    <!-- Panel Ubah Komponen (Modal) -->
    @if ($editingId)
        @php
            $editItem = $items->firstWhere('id', $editingId);
        @endphp
        @if ($editItem)
            @php
                $et = $edit['cost_type'] !== '' ? $edit['cost_type'] : $editItem->cost_type;
                $eCap = $editItem->unit_capacity && in_array($et, $unitTypes, true);
                $eOptions = $editItem->cost_type === 'percentage'
                    ? ['percentage' => \App\Support\Options::COST_TYPES['percentage']]
                    : array_diff_key(\App\Support\Options::COST_TYPES, ['percentage' => true]);
            @endphp
            <div class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/50 backdrop-blur-sm p-4">
                <div class="bg-white rounded-2xl shadow-xl w-full max-w-lg max-h-[90vh] overflow-y-auto p-6 border border-slate-200">
                    <div class="flex items-start justify-between mb-1">
                        <h3 class="text-lg font-semibold text-slate-900">Ubah Komponen</h3>
                        <button wire:click="closeEdit" class="text-slate-400 hover:text-slate-600">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                        </button>
                    </div>
                    <p class="text-sm text-slate-500 mb-5">{{ $editItem->item_name }}</p>

                    <form wire:submit="saveEdit" class="space-y-4">
                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-1.5">Tipe biaya</label>
                            <select wire:model.live="edit.cost_type" class="w-full rounded-xl border-slate-300 bg-slate-50 text-sm focus:bg-white focus:ring-indigo-500 py-2.5 px-3.5 transition-colors">
                                @foreach ($eOptions as $val => $label)
                                    <option value="{{ $val }}">{{ $label }}</option>
                                @endforeach
                            </select>
                            @error('edit.cost_type')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                        </div>

                        @if ($et === 'per_person')
                            <div>
                                <label class="block text-sm font-medium text-slate-700 mb-1.5">Jumlah orang</label>
                                <input type="number" min="1" max="{{ $paxNow }}" wire:model="edit.people_count"
                                       placeholder="Semua peserta ({{ $paxNow }})"
                                       class="w-full rounded-xl border-slate-300 bg-slate-50 text-sm focus:bg-white focus:ring-indigo-500 py-2.5 px-3.5 transition-colors">
                                <p class="mt-1 text-xs text-slate-500">Kosongkan bila berlaku untuk semua peserta.</p>
                                @error('edit.people_count')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                            </div>
                        @elseif ($et === 'percentage')
                            <p class="text-xs text-slate-500">Biaya persentase mengikuti pengaturan komponen di data master.</p>
                        @elseif (! $eCap)
                            <div>
                                <label class="block text-sm font-medium text-slate-700 mb-1.5">{{ $qtyLabels[$et] ?? 'Jumlah' }}</label>
                                <input type="number" step="1" min="1" wire:model="edit.quantity"
                                       class="w-full rounded-xl border-slate-300 bg-slate-50 text-sm focus:bg-white focus:ring-indigo-500 py-2.5 px-3.5 transition-colors">
                                @error('edit.quantity')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                            </div>
                        @endif

                        @if (in_array($et, $durationTypes, true))
                            <div>
                                <label class="block text-sm font-medium text-slate-700 mb-1.5">{{ $et === 'per_day' ? 'Jumlah hari' : 'Jumlah malam' }}</label>
                                <input type="number" step="0.5" min="0.5" wire:model="edit.duration_qty"
                                       class="w-full rounded-xl border-slate-300 bg-slate-50 text-sm focus:bg-white focus:ring-indigo-500 py-2.5 px-3.5 transition-colors">
                                @error('edit.duration_qty')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                            </div>
                        @endif

                        @if ($eCap)
                            <div class="rounded-xl bg-amber-50 border border-amber-100 p-4 space-y-4">
                                <p class="text-xs text-amber-800 leading-relaxed">
                                    Sistem menghitung <span class="font-bold">{{ app(\App\Services\TripCalculator::class)->calculateRoomRequirement($paxNow, (int) $editItem->unit_capacity) }}</span> unit
                                    (kapasitas {{ $editItem->unit_capacity }} org/unit). Isi di bawah hanya jika ingin menggantinya secara manual.
                                </p>
                                <div>
                                    <label class="block text-sm font-medium text-amber-900 mb-1.5">Jumlah unit manual</label>
                                    <input type="number" step="1" min="1" wire:model="edit.override_quantity"
                                           class="w-full rounded-lg border-amber-300 bg-white text-sm focus:ring-amber-500 py-2 px-3">
                                    @error('edit.override_quantity')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-amber-900 mb-1.5">Alasan</label>
                                    <input type="text" wire:model="edit.override_reason" placeholder="Contoh: Kurang 1 kamar"
                                           class="w-full rounded-lg border-amber-300 bg-white text-sm focus:ring-amber-500 py-2 px-3">
                                    @error('edit.override_reason')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                                </div>
                            </div>
                        @endif

                        <label class="flex items-center gap-2.5 text-sm text-slate-700 cursor-pointer">
                            <input type="checkbox" wire:model="edit.is_required" class="h-4 w-4 rounded border-slate-300 text-indigo-600 focus:ring-indigo-500">
                            Wajib (harus punya actual cost saat rekonsiliasi)
                        </label>

                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-1.5">Catatan</label>
                            <input type="text" wire:model="edit.notes" class="w-full rounded-xl border-slate-300 bg-slate-50 text-sm focus:bg-white focus:ring-indigo-500 py-2.5 px-3.5 transition-colors">
                            @error('edit.notes')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                        </div>

                        <div class="flex justify-end gap-3 pt-4 border-t border-slate-100">
                            <button type="button" wire:click="closeEdit"
                                    class="px-4 py-2.5 text-sm font-medium rounded-xl border border-slate-300 text-slate-700 hover:bg-slate-50 transition-colors">Batal</button>
                            <button type="submit" class="px-4 py-2.5 text-sm font-medium rounded-xl bg-indigo-600 text-white hover:bg-indigo-700 transition-colors shadow-sm">Simpan Perubahan</button>
                        </div>
                    </form>
                </div>
            </div>
        @endif
    @endif

    <!-- Panel Pembatalan (Modal) -->
    @if ($showCancel)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/50 backdrop-blur-sm p-4">
            <div class="bg-white rounded-2xl shadow-xl w-full max-w-md p-6 border border-slate-200">
                <h3 class="text-lg font-semibold text-red-600 mb-1">Batalkan Trip</h3>
                <p class="text-sm text-slate-500 mb-5">Pembatalan tidak bisa diurungkan. Alasan wajib diisi dan akan tercatat di audit log.</p>
                <form wire:submit="cancelTrip" class="space-y-4">
                    <div>
                        <textarea wire:model="cancelReason" rows="3" placeholder="Tuliskan alasan pembatalan secara detail..."
                                  class="w-full rounded-xl border-slate-300 bg-slate-50 text-sm focus:bg-white focus:ring-red-500 focus:border-red-500 py-3 px-3.5 transition-colors"></textarea>
                        @error('cancelReason')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                    </div>
                    <div class="flex justify-end gap-3 pt-2">
                        <button type="button" wire:click="$set('showCancel', false)"
                                class="px-4 py-2.5 text-sm font-medium rounded-xl border border-slate-300 text-slate-700 hover:bg-slate-50 transition-colors">Tutup</button>
                        <button type="submit" class="px-4 py-2.5 text-sm font-medium rounded-xl bg-red-600 text-white hover:bg-red-700 transition-colors shadow-sm">Ya, Batalkan Trip</button>
                    </div>
                </form>
            </div>
        </div>
    @endif
</div>