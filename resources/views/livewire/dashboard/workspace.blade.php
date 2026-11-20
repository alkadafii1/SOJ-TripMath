<div class="space-y-6">
    <header class="flex flex-wrap items-end justify-between gap-4 border-b border-gray-200 pb-5">
        <div>
            <p class="text-xs font-semibold uppercase tracking-widest text-indigo-600">Sunrise of Java / Overview</p>
            <h1 class="mt-2 text-2xl font-semibold text-gray-900">Dashboard</h1>
            <p class="mt-1 text-sm text-gray-500">Kondisi trip, jadwal keberangkatan, dan hasil bisnis dalam satu tempat.</p>
        </div>
        <div class="flex flex-wrap items-end gap-2">
            <label class="text-xs text-gray-500">Periode operasional
                <select wire:model.live="preset" class="mt-1 block rounded-lg border-gray-300 text-sm">
                    @foreach ($presets as $key => $label)<option value="{{ $key }}">{{ $label }}</option>@endforeach
                </select>
            </label>
            @if ($preset === 'custom')
                <label class="text-xs text-gray-500">Dari<input aria-label="Tanggal mulai operasional" type="date" wire:model.live="from" class="mt-1 block rounded-lg border-gray-300 text-sm"></label>
                <label class="text-xs text-gray-500">Sampai<input aria-label="Tanggal akhir operasional" type="date" wire:model.live="to" class="mt-1 block rounded-lg border-gray-300 text-sm"></label>
            @endif
        </div>
    </header>
    @if ($error)<p role="alert" class="rounded-lg bg-amber-50 p-3 text-sm text-amber-800">{{ $error }}</p>@endif
    <p class="text-xs text-gray-500">Ringkasan dan sebaran status berdasarkan tanggal mulai trip: {{ $period->label() }}. Hanya data yang boleh Anda lihat.</p>

    @php
        $cards = [
            ['Total Trip', $stats['total'], $stats['cancelled'].' trip dibatalkan', []],
            ['Dalam Persiapan', $stats['preparation'], 'Draft, Estimated, Negotiation', ['draft', 'estimated', 'negotiation']],
            ['Siap Berangkat', $stats['ready'], 'Confirmed, Deposit Paid, Paid', ['confirmed', 'deposit_paid', 'paid']],
            ['Sedang Berjalan', $stats['ongoing'], 'Trip berstatus Ongoing', ['ongoing']],
            ['Trip Selesai', $stats['finished'], $stats['completed'].' completed / '.$stats['reconciled'].' reconciled', ['completed', 'reconciled']],
        ];
    @endphp
    <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 xl:grid-cols-5">
        @foreach ($cards as [$label, $value, $description, $statuses])
            <a href="{{ route('trips.index', ['statuses' => implode(',', $statuses), 'startFrom' => $period->from->toDateString(), 'startTo' => $period->to->toDateString()]) }}" wire:navigate class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm transition hover:border-indigo-400 focus:outline-none focus:ring-2 focus:ring-indigo-500">
                <p class="text-sm font-medium text-gray-600">{{ $label }}</p>
                <p class="mt-3 text-3xl font-semibold tracking-tight text-gray-900">{{ $value }}</p>
                <p class="mt-2 text-xs leading-relaxed text-gray-500">{{ $description }}</p>
            </a>
        @endforeach
    </div>

    <section class="overflow-hidden rounded-xl border border-gray-200 bg-white">
        <div class="flex flex-wrap items-end justify-between gap-3 p-5">
            <div><h2 class="font-semibold text-gray-900">Jadwal trip</h2><p class="mt-1 text-xs text-gray-500">Tidak mengikuti filter periode. Maksimal 10 trip per tab, urut tanggal mulai.</p></div>
            <div class="flex flex-wrap gap-1 rounded-lg bg-gray-100 p-1" role="tablist" aria-label="Jadwal trip">
                <button type="button" role="tab" aria-selected="{{ $tab === 'ongoing' ? 'true' : 'false' }}" wire:click="$set('tab', 'ongoing')" class="rounded-md px-3 py-2 text-xs font-medium {{ $tab === 'ongoing' ? 'bg-white text-indigo-700 shadow-sm' : 'text-gray-600' }}">Sedang Berjalan</button>
                <button type="button" role="tab" aria-selected="{{ $tab === 'upcoming' ? 'true' : 'false' }}" wire:click="$set('tab', 'upcoming')" class="rounded-md px-3 py-2 text-xs font-medium {{ $tab === 'upcoming' ? 'bg-white text-indigo-700 shadow-sm' : 'text-gray-600' }}">Keberangkatan 14 Hari</button>
            </div>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full min-w-[680px] text-left text-sm">
                <thead class="border-y border-gray-100 bg-gray-50 text-xs text-gray-500"><tr><th class="px-5 py-3">Trip / Customer</th><th class="px-4 py-3">Tanggal</th><th class="px-4 py-3">Pax</th><th class="px-4 py-3">Staff / Partner</th><th class="px-4 py-3">Status</th><th class="px-5 py-3"><span class="sr-only">Detail</span></th></tr></thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse ($schedule as $trip)
                        <tr wire:key="schedule-{{ $trip->id }}" class="hover:bg-gray-50">
                            <td class="px-5 py-4"><p class="font-medium text-gray-900">{{ $trip->trip_name }}</p><p class="mt-1 text-xs text-gray-500">{{ $trip->trip_code }} / {{ $trip->customer_name }}</p></td>
                            <td class="px-4 py-4 text-xs text-gray-600">{{ $trip->start_date->format('d M Y') }}<br><span class="text-gray-400">s/d {{ $trip->end_date->format('d M Y') }}</span></td>
                            <td class="px-4 py-4">{{ $trip->effectivePax() }}</td>
                            <td class="px-4 py-4 text-xs text-gray-600">{{ $trip->staff?->name ?? 'Staff belum ditugaskan' }}<br><span class="text-gray-400">{{ $trip->partner?->name ?? 'Partner belum ditugaskan' }}</span></td>
                            <td class="px-4 py-4"><span class="whitespace-nowrap rounded-full px-2 py-1 text-xs {{ $trip->statusBadgeClass() }}">{{ \App\Models\Trip::STATUS[$trip->status] }}</span></td>
                            <td class="px-5 py-4"><a href="{{ route('trips.show', $trip) }}" wire:navigate class="font-medium text-indigo-600 hover:underline">Detail<span class="sr-only"> {{ $trip->trip_name }}</span></a></td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="px-5 py-10 text-center text-gray-500">{{ $tab === 'ongoing' ? 'Belum ada trip yang sedang berjalan.' : 'Belum ada keberangkatan dalam 14 hari ke depan.' }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>

    <section class="rounded-xl border border-gray-200 bg-white p-5">
        <h2 class="font-semibold text-gray-900">Sebaran status trip</h2>
        <p class="mt-1 text-xs text-gray-500">Jumlah trip per status asli dalam periode operasional.</p>
        @php $max = max(1, max(array_column($stats['statuses'], 'count'))); @endphp
        <div class="mt-5 grid gap-x-8 gap-y-3 md:grid-cols-2">
            @foreach ($stats['statuses'] as $row)
                <div class="grid grid-cols-[110px_1fr_28px] items-center gap-3 text-xs">
                    <span class="text-gray-600">{{ $row['label'] }}</span>
                    <div class="h-2 overflow-hidden rounded-full bg-gray-100"><div class="h-full rounded-full {{ $row['status'] === 'cancelled' ? 'bg-gray-400' : 'bg-indigo-500' }}" style="width: {{ $row['count'] / $max * 100 }}%"></div></div>
                    <span class="text-right font-semibold text-gray-800">{{ $row['count'] }}</span>
                </div>
            @endforeach
        </div>
    </section>

    @can('view-finance')
        <livewire:dashboard.summary />
    @endcan

    @if ($actions)
        <section class="rounded-xl border border-amber-200 bg-amber-50/50 p-5">
            <h2 class="text-sm font-semibold text-amber-900">Perlu perhatian</h2>
            <p class="mt-1 text-xs text-amber-800">Pemeriksaan saat ini, tidak mengikuti periode. Satu trip bisa masuk beberapa kategori.</p>
            <div class="mt-3 divide-y divide-amber-200/60">
                @foreach ($actions as $action)
                    <details class="py-2 text-sm">
                        <summary class="cursor-pointer text-amber-900">{{ $action['count'] }} trip: {{ $action['title'] }}</summary>
                        <ul class="mt-2 space-y-2 pl-4 text-xs">
                            @foreach ($action['trips'] as $trip)
                                <li><a href="{{ $trip['url'] }}" wire:navigate class="text-indigo-700 hover:underline">{{ $trip['code'] }} / {{ $trip['name'] }}</a></li>
                            @endforeach
                            @if ($action['count'] > count($action['trips']))<li class="text-amber-800">Menampilkan {{ count($action['trips']) }} dari {{ $action['count'] }} trip.</li>@endif
                        </ul>
                    </details>
                @endforeach
            </div>
        </section>
    @endif
</div>
