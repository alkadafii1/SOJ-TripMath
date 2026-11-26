@php
    $rp = fn ($v) => \App\Support\Money::rp($v);
    $pct = fn ($v) => \App\Support\Money::percent($v);
    $opt = \App\Support\Options::class;
    $calc = app(\App\Services\PricingCalculator::class);
    $plain = fn ($v) => ($v === null || $v === '') ? '' : (str_contains((string) $v, '.') ? rtrim(rtrim((string) $v, '0'), '.') : (string) $v);
    $nice = fn ($v) => number_format((float) $v, 0, ',', '.');
    $std = $costs['standard'];
    $neg = $costs['negotiated'];
    $modeNow = $pricing?->pricing_mode ?? $mode;
    $perPaxMode = $modeNow === 'per_pax';
    $totalHint = fn ($v) => ($perPaxMode && preg_match('/^\d{1,13}(\.\d{1,2})?$/', trim((string) $v)))
        ? '= '.$rp($calc->calculateTotalFromPerPax(trim((string) $v), $pax)).' untuk '.$pax.' pax'
        : '';
    $inPreStage = in_array($trip->status, ['draft', 'estimated'], true);
@endphp

<div class="py-8">
    <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">

        <!-- Header Halaman -->
        <div class="space-y-4">
            <nav aria-label="Breadcrumb" class="flex flex-wrap items-center gap-1.5 text-xs text-slate-500">
                <a href="{{ route('dashboard') }}" wire:navigate class="rounded hover:text-indigo-600 focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500">Beranda</a>
                <svg class="h-3 w-3 text-slate-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M9 5l7 7-7 7" /></svg>
                <a href="{{ route('trips.index') }}" wire:navigate class="rounded hover:text-indigo-600 focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500">Trip</a>
                <svg class="h-3 w-3 text-slate-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M9 5l7 7-7 7" /></svg>
                <span aria-current="page" class="font-semibold text-indigo-600">Harga &amp; Penawaran</span>
            </nav>

            <div class="flex flex-wrap items-end justify-between gap-3">
                <div>
                    <h1 class="text-2xl font-extrabold tracking-tight text-slate-900">Harga &amp; Penawaran</h1>
                    <p class="mt-1 text-sm text-slate-500">Atur biaya negosiasi vendor, harga rekomendasi, dan harga final untuk trip ini.</p>
                </div>
                <a href="{{ route('trips.show', $trip->id) }}" wire:navigate
                   class="inline-flex items-center rounded-xl bg-white px-3.5 py-2 text-sm font-semibold text-slate-600 ring-1 ring-slate-200 transition-colors hover:bg-slate-50 hover:text-slate-900 focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500">&larr; Kembali ke trip</a>
            </div>

            <!-- Bar info trip -->
            <div class="grid grid-cols-1 gap-5 rounded-2xl bg-white p-5 shadow-[0_8px_30px_-14px_rgba(79,70,229,0.18)] ring-1 ring-slate-100 sm:grid-cols-[minmax(0,1.6fr)_1fr_1fr]">
                <div class="flex items-center gap-3">
                    <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-indigo-50 text-indigo-600" aria-hidden="true"><x-icon name="map" class="h-5 w-5" /></span>
                    <div class="min-w-0">
                        <p class="text-[11px] font-semibold uppercase tracking-[0.1em] text-slate-500">Trip</p>
                        <p class="break-words font-bold text-slate-900">{{ $trip->trip_name }}</p>
                        <p class="text-xs font-medium text-indigo-600">{{ $trip->trip_code }}</p>
                    </div>
                </div>
                <div class="flex items-center gap-3">
                    <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-sky-50 text-sky-600" aria-hidden="true"><x-icon name="users" class="h-5 w-5" /></span>
                    <div>
                        <p class="text-[11px] font-semibold uppercase tracking-[0.1em] text-slate-500">Peserta</p>
                        <p class="font-bold tabular-nums text-slate-900">{{ $pax }} pax</p>
                    </div>
                </div>
                <div class="flex items-center gap-3">
                    <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-emerald-50 text-emerald-600" aria-hidden="true"><x-icon name="check-circle" class="h-5 w-5" /></span>
                    <div>
                        <p class="text-[11px] font-semibold uppercase tracking-[0.1em] text-slate-500">Status</p>
                        <span class="mt-0.5 inline-flex items-center gap-1.5 rounded-full px-2.5 py-1 text-xs font-semibold {{ $trip->statusBadgeClass() }}">
                            <span class="h-1.5 w-1.5 rounded-full bg-current opacity-70" aria-hidden="true"></span>
                            {{ \App\Models\Trip::STATUS[$trip->status] ?? $trip->status }}
                        </span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Flash Messages -->
        @if (session('status'))
            <div class="flex items-center gap-3 rounded-xl bg-emerald-50 p-4 text-sm text-emerald-800 ring-1 ring-emerald-100">
                <svg class="h-5 w-5 shrink-0 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                {{ session('status') }}
            </div>
        @endif
        @if (session('error'))
            <div class="flex items-center gap-3 rounded-xl bg-red-50 p-4 text-sm text-red-800 ring-1 ring-red-100">
                <svg class="h-5 w-5 shrink-0 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                {{ session('error') }}
            </div>
        @endif

        @error('negotiationReady')
            <div role="alert" class="rounded-md bg-red-50 p-3 text-sm text-red-800">{{ $message }}</div>
        @enderror

        <!-- Ringkasan Biaya -->
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
            <!-- Standard Cost -->
            <div class="rounded-2xl bg-white p-5 shadow-[0_8px_30px_-14px_rgba(79,70,229,0.18)] ring-1 ring-slate-100">
                <div class="flex items-start justify-between gap-3">
                    <p class="text-[11px] font-semibold uppercase tracking-[0.1em] text-slate-500">Standard Cost</p>
                    <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-indigo-50 text-indigo-600" aria-hidden="true"><x-icon name="database" class="h-[18px] w-[18px]" /></span>
                </div>
                <p class="mt-3 text-2xl font-extrabold tracking-tight tabular-nums text-slate-900">{{ $rp($std['total']) }}</p>
            </div>
            <!-- Negotiated Cost -->
            <div class="rounded-2xl bg-white p-5 shadow-[0_8px_30px_-14px_rgba(79,70,229,0.18)] ring-1 ring-slate-100">
                <div class="flex items-start justify-between gap-3">
                    <p class="text-[11px] font-semibold uppercase tracking-[0.1em] text-slate-500">Negotiated Cost</p>
                    <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-violet-50 text-violet-600" aria-hidden="true"><x-icon name="tag" class="h-[18px] w-[18px]" /></span>
                </div>
                <p class="mt-3 text-2xl font-extrabold tracking-tight tabular-nums text-slate-900">{{ $rp($neg['total']) }}</p>
            </div>
            <!-- Vendor Saving -->
            <div class="{{ $costs['saving'] < 0 ? 'bg-white border-white-200' : 'bg-[#ffffff] border-white-200' }} p-5 rounded-2xl border shadow-sm flex flex-col justify-between transition-colors">
    <div class="flex items-start justify-between">
        <div>
            <p class="{{ $costs['saving'] < 0 ? 'text-black' : 'text-black' }} text-[11px] font-semibold uppercase tracking-wider mb-1">Vendor Saving</p>
            <p class="{{ $costs['saving'] < 0 ? 'text-black' : 'text-black' }} text-2xl font-bold">
                {{ $rp(abs((float) $costs['saving'])) }} <span class="text-sm font-medium">({{ $pct(abs((float) $costs['saving_percent'])) }})</span>
            </p>
        </div>
        <div class="{{ $costs['saving'] < 0 ? 'bg-red-100 text-red-600' : 'bg-green-100 text-green-600' }} w-8 h-8 rounded-full flex items-center justify-center shrink-0">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
        </div>
    </div>
</div>
            <!-- Biaya per orang -->
            <div class="rounded-2xl bg-white p-5 shadow-[0_8px_30px_-14px_rgba(79,70,229,0.18)] ring-1 ring-slate-100">
                <div class="flex items-start justify-between gap-3">
                    <p class="text-[11px] font-semibold uppercase tracking-[0.1em] text-slate-500">Biaya per Orang</p>
                    <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-sky-50 text-sky-600" aria-hidden="true"><x-icon name="users" class="h-[18px] w-[18px]" /></span>
                </div>
                <p class="mt-3 text-2xl font-extrabold tracking-tight tabular-nums text-slate-900">{{ $rp($neg['cost_per_person']) }}</p>
            </div>
        </div>

        <!-- Peringatan tarif Rp0 -->
        @if (! empty($zeroCosts))
            <div class="rounded-2xl bg-amber-50 p-5 text-sm text-amber-900 ring-1 ring-amber-200">
                <div class="flex items-start gap-3">
                    <svg class="w-5 h-5 text-amber-600 shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path></svg>
                    <div>
                        <p class="font-semibold text-amber-900">{{ count($zeroCosts) }} komponen masih bertarif Rp0</p>
                        <p class="mt-1 text-amber-800">
                            {{ implode(', ', array_slice($zeroCosts, 0, 5)) }}@if (count($zeroCosts) > 5), dan {{ count($zeroCosts) - 5 }} lainnya @endif.
                            Biaya dan margin di halaman ini bisa tidak akurat. Tarif disalin saat trip dibuat, jadi mengisi tarif di Data Master tidak mengubah trip ini.
                            Isi "Tarif negosiasi" di bawah, atau buat trip baru setelah tarif master terisi.
                        </p>
                    </div>
                </div>
            </div>
        @endif

        <!-- Negosiasi Vendor -->
        <div class="overflow-hidden rounded-2xl bg-white shadow-[0_8px_30px_-14px_rgba(79,70,229,0.18)] ring-1 ring-slate-100">
            <div class="flex items-start justify-between gap-3 border-b border-slate-100 px-6 py-5">
                <div class="flex items-start gap-3">
                    <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-indigo-50 text-indigo-600" aria-hidden="true"><x-icon name="tag" class="h-5 w-5" /></span>
                    <div>
                        <h3 class="text-base font-bold text-slate-900">Negosiasi Vendor</h3>
                        <p class="mt-0.5 text-sm text-slate-500">Isi tarif hasil negosiasi per komponen. Kosong berarti sama dengan tarif standar. Data master tidak berubah.</p>
                    </div>
                </div>
            </div>

            <form wire:submit="saveNegotiatedCosts">
                <div class="overflow-x-auto">
                    <table class="min-w-full text-sm">
                        <thead class="border-b border-slate-100 bg-white">
                            <tr>
                                <th class="px-4 py-3.5 text-left text-[11px] font-semibold uppercase tracking-[0.1em] text-slate-500 whitespace-nowrap">Komponen</th>
                                <th class="px-4 py-3.5 text-left text-[11px] font-semibold uppercase tracking-[0.1em] text-slate-500 whitespace-nowrap">Tipe biaya</th>
                                <th class="px-4 py-3.5 text-right text-[11px] font-semibold uppercase tracking-[0.1em] text-slate-500 whitespace-nowrap">Tarif standar</th>
                                <th class="px-4 py-3.5 text-right text-[11px] font-semibold uppercase tracking-[0.1em] text-slate-500 whitespace-nowrap">Tarif negosiasi</th>
                                <th class="px-4 py-3.5 text-right text-[11px] font-semibold uppercase tracking-[0.1em] text-slate-500 whitespace-nowrap">Subtotal standar</th>
                                <th class="px-4 py-3.5 text-right text-[11px] font-semibold uppercase tracking-[0.1em] text-slate-500 whitespace-nowrap">Subtotal negosiasi</th>
                                <th class="px-4 py-3.5 text-right text-[11px] font-semibold uppercase tracking-[0.1em] text-slate-500 whitespace-nowrap">Hemat</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            @forelse ($items as $item)
                                @php
                                    $sl = $std['lines'][$item->id]['amount'] ?? '0';
                                    $nl = $neg['lines'][$item->id]['amount'] ?? '0';
                                @endphp
                                <tr wire:key="neg-{{ $item->id }}" class="transition-colors hover:bg-slate-50/70">
                                    <td class="px-4 py-3.5">
                                        <div class="font-semibold text-slate-900">{{ $item->item_name }}</div>
                                        <div class="text-xs text-slate-500 mt-0.5">{{ $item->category }}</div>
                                    </td>
                                    <td class="px-4 py-3.5 text-slate-700">{{ $opt::COST_TYPES[$item->cost_type] ?? $item->cost_type }}</td>
                                    <td class="px-4 py-3.5 text-right tabular-nums text-slate-700">
                                        {{ $item->cost_type === 'percentage' ? $plain($item->percentage).'%' : $rp($item->standard_cost) }}
                                    </td>
                                    <td class="px-4 py-3.5 text-right">
                                        @if ($item->cost_type === 'percentage')
                                            <span class="text-slate-400">-</span>
                                        @elseif ($costEditable)
                                            <div class="flex justify-end">
                                                <x-money-input model="negotiated.{{ $item->id }}" class="w-full max-w-[150px]" input-class="w-full rounded-xl border border-slate-200 bg-slate-50 py-2.5 pl-10 pr-3 text-sm text-slate-900 placeholder:text-slate-400 transition-colors focus:border-indigo-500 focus:bg-white focus:ring-2 focus:ring-indigo-500/20 disabled:opacity-60" placeholder="{{ $nice($item->standard_cost) }}" />
                                            </div>
                                            @error('negotiated.'.$item->id)<p class="mt-1.5 text-xs text-red-600">{{ $message }}</p>@enderror
                                        @else
                                            {{ $item->negotiated_cost !== null ? $rp($item->negotiated_cost) : 'sama dengan standar' }}
                                        @endif
                                    </td>
                                    <td class="px-4 py-3.5 text-right tabular-nums text-slate-700">{{ $rp($sl) }}</td>
                                    <td class="px-4 py-3.5 text-right font-semibold tabular-nums text-slate-900">{{ $rp($nl) }}</td>
                                    <td class="px-6 py-3 text-right font-medium {{ $calc->calculateVendorSaving($sl, $nl)['amount'] < 0 ? 'text-red-600' : 'text-green-600' }}">
                                        {{ $rp(abs((float) $calc->calculateVendorSaving($sl, $nl)['amount'])) }}
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="7" class="px-4 py-12 text-center text-slate-500">Belum ada komponen biaya pada trip ini.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                @if ($costEditable)
                    <div class="flex flex-col items-start gap-4 border-t border-slate-100 bg-slate-50/60 p-6 sm:flex-row sm:items-end">
                        <div class="flex-1 w-full">
                            <label class="mb-1.5 block text-xs font-semibold text-slate-700">Catatan negosiasi (opsional, masuk audit log)</label>
                            <input type="text" wire:model="negotiationNote" placeholder="Contoh: Diskon vendor untuk 10 kamar" class="w-full rounded-xl border border-slate-200 bg-slate-50 px-3.5 py-2.5 text-sm text-slate-900 placeholder:text-slate-400 transition-colors focus:border-indigo-500 focus:bg-white focus:ring-2 focus:ring-indigo-500/20 disabled:opacity-60">
                        </div>
                        <button type="submit" class="inline-flex items-center justify-center gap-2 rounded-xl bg-gradient-to-r from-indigo-600 to-violet-600 px-5 py-2.5 text-sm font-semibold text-white shadow-lg shadow-indigo-600/25 transition hover:from-indigo-700 hover:to-violet-700 focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500 focus-visible:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-70 w-full whitespace-nowrap sm:w-auto">
                            Simpan Biaya Negosiasi
                        </button>
                    </div>
                @elseif ($canNegotiate)
                    <p class="text-xs text-gray-500">
                        Biaya negosiasi terkunci karena trip berstatus {{ \App\Models\Trip::STATUS[$trip->status] ?? $trip->status }}.
                        Kembalikan ke Estimated untuk mengubahnya.
                    </p>
                @endif
            </form>
        </div>

        <!-- 3. Harga Rekomendasi (Sebelum Negosiasi) -->
        @if ($inPreStage)
            <div class="overflow-hidden rounded-2xl bg-white shadow-[0_8px_30px_-14px_rgba(79,70,229,0.18)] ring-1 ring-slate-100">
                <div class="flex items-start justify-between gap-3 border-b border-slate-100 px-6 py-5">
                    <div class="flex items-start gap-3">
                        <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-emerald-50 text-emerald-600" aria-hidden="true"><x-icon name="check-circle" class="h-5 w-5" /></span>
                        <div>
                            <h3 class="text-base font-bold text-slate-900">Harga Rekomendasi</h3>
                        </div>
                    </div>
                </div>
                <div class="p-6 space-y-5">
                    @if ($pricing)
                        <div class="flex items-start gap-3 rounded-xl bg-slate-50 p-4 text-sm text-slate-600 ring-1 ring-slate-100">
                            <svg class="w-5 h-5 text-slate-400 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                            <p>Harga sebelumnya: Recommended <span class="font-semibold text-slate-900">{{ $rp($pricing->recommended_price) }}</span>@if ($pricing->final_price !== null), Final <span class="font-semibold text-slate-900">{{ $rp($pricing->final_price) }}</span>@endif. Harga rekomendasi dihitung ulang saat negosiasi dimulai lagi.</p>
                        </div>
                    @endif

                    @if (! $preview)
                        <div class="flex items-center gap-3 rounded-xl bg-amber-50 p-4 text-sm text-amber-800 ring-1 ring-amber-200">
                            <svg class="w-5 h-5 text-amber-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path></svg>
                            Belum ada aturan harga aktif. Minta Owner membuatnya di menu Aturan Harga.
                        </div>
                    @else
                        @php
                            $rule = $preview['rule'];
                        @endphp
                        <p class="text-sm text-slate-600 leading-relaxed">
                            Aturan aktif: <span class="font-semibold text-slate-900">{{ $rule->method_label }} {{ $pct($rule->rate_percent) }}</span>, {{ strtolower($rule->rounding_label) }}, batas margin minimum {{ $rule->min_margin_threshold !== null ? $pct($rule->min_margin_threshold) : 'tidak diatur' }}
                            @if ($rule->block_below_min) (diblokir bila di bawah batas)
                            @elseif ($rule->require_approval) (butuh persetujuan Owner bila di bawah batas)
                            @endif.
                        </p>

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
                            <div>
                                <label class="mb-1.5 block text-xs font-semibold text-slate-700">Mode harga</label>
                                <select wire:model.live="mode" class="w-full rounded-xl border border-slate-200 bg-slate-50 py-2.5 pl-3.5 pr-9 text-sm text-slate-900 transition-colors focus:border-indigo-500 focus:bg-white focus:ring-2 focus:ring-indigo-500/20">
                                    @foreach ($opt::PRICING_MODES as $val => $label)
                                        @if ($val !== 'per_pax' || $trip->trip_type === 'open_trip')
                                            <option value="{{ $val }}">{{ $label }}</option>
                                        @endif
                                    @endforeach
                                </select>
                            </div>
                            @if ($mode === 'package')
                                <div>
                                    <label class="mb-1.5 block text-xs font-semibold text-slate-700">Harga paket total</label>
                                    <x-money-input model="packagePrice" :live="true" class="w-full" input-class="w-full rounded-xl border border-slate-200 bg-slate-50 py-2.5 pl-10 pr-3 text-sm text-slate-900 placeholder:text-slate-400 transition-colors focus:border-indigo-500 focus:bg-white focus:ring-2 focus:ring-indigo-500/20 disabled:opacity-60" />
                                    @error('packagePrice')<p class="mt-1.5 text-xs text-red-600">{{ $message }}</p>@enderror
                                </div>
                            @endif
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
                            <div class="rounded-2xl bg-gradient-to-br from-indigo-50 to-violet-50 p-5 ring-1 ring-indigo-100">
                                <p class="mb-1 text-[11px] font-semibold uppercase tracking-[0.1em] text-indigo-600">Harga rekomendasi</p>
                                @if ($mode === 'per_pax' && $preview['rec']['per_pax'] !== null)
                                    <p class="text-3xl font-extrabold tracking-tight tabular-nums text-indigo-900">{{ $rp($preview['rec']['per_pax']) }} <span class="text-base font-medium">per pax</span></p>
                                    <p class="mt-1 text-xs text-indigo-700">Total {{ $preview['recommended'] !== null ? $rp($preview['recommended']) : '-' }} untuk {{ $pax }} pax</p>
                                @else
                                    <p class="text-3xl font-extrabold tracking-tight tabular-nums text-indigo-900">{{ $preview['recommended'] !== null ? $rp($preview['recommended']) : '-' }}</p>
                                @endif
                                <p class="mt-2 text-xs text-indigo-700 leading-relaxed">
                                    Dari biaya negosiasi {{ $rp($preview['rec']['cost']) }} dengan {{ $rule->method_label }} {{ $pct($rule->rate_percent) }}
                                </p>
                            </div>
                            @if ($mode === 'package')
                                <div class="rounded-2xl bg-slate-50 p-5 ring-1 ring-slate-100">
                                    <p class="mb-1 text-[11px] font-semibold uppercase tracking-[0.1em] text-slate-500">Saran mesin (biaya + margin)</p>
                                    <p class="text-2xl font-extrabold tracking-tight tabular-nums text-slate-900">{{ $rp($preview['rec']['engine_total']) }}</p>
                                </div>
                            @endif
                        </div>

                        @if ($preview['eval'] && $preview['eval']['below_min'])
                            <div class="flex items-center gap-3 rounded-xl bg-red-50 p-4 text-sm text-red-800 ring-1 ring-red-100">
                                <svg class="w-5 h-5 text-red-600 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                                Margin pada harga ini {{ $pct($preview['eval']['margin'] ?? 0) }}, di bawah batas minimum {{ $pct($rule->min_margin_threshold) }}.
                            </div>
                        @endif

                        @if ($showProfit && $preview['eval'])
                            <div class="rounded-2xl border border-dashed border-amber-300 bg-amber-50 p-5">
                                <p class="mb-3 text-[11px] font-bold uppercase tracking-[0.1em] text-amber-700">Estimasi (bukan Actual Profit)</p>
                                <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 text-sm">
                                    <div>
                                        <p class="text-amber-800 mb-0.5">Estimated Profit</p>
                                        <p class="font-semibold text-slate-900">{{ $rp($preview['eval']['profit']) }}</p>
                                    </div>
                                    <div>
                                        <p class="text-amber-800 mb-0.5">Estimated Margin</p>
                                        <p class="font-semibold text-slate-900">{{ $pct($preview['eval']['margin'] ?? 0) }}</p>
                                    </div>
                                    <div>
                                        <p class="text-amber-800 mb-0.5">Profit per Orang</p>
                                        <p class="font-semibold text-slate-900">{{ $preview['eval']['profit_per_pax'] !== null ? $rp($preview['eval']['profit_per_pax']) : '-' }}</p>
                                    </div>
                                </div>
                            </div>
                        @endif

                    @if ($canNegotiate)
                        @if ($trip->status === 'estimated')
                            <div class="flex justify-end">
                                <button wire:click="startNegotiation"
                                        wire:confirm="Harga rekomendasi akan disimpan dan struktur trip (pax dan komponen) terkunci. Lanjutkan?"
                                        class="px-4 py-2 text-sm font-medium rounded-md bg-emerald-600 text-white hover:bg-emerald-500">
                                    Mulai negosiasi harga
                                </button>
                            </div>
                        @else
                            <p class="text-xs text-gray-500">Tandai trip sebagai Estimated di halaman trip sebelum memulai negosiasi harga.</p>
                        @endif
                    @endif
                @endif
            </div>
        @endif

        {{-- 3. Negosiasi harga dengan customer --}}
        @if ($pricing && ! $inPreStage)
            @php
                $recPerPax = $perPaxMode ? $calc->calculatePricePerPax((string) $pricing->recommended_price, $pax) : null;
                $disc = $pricing->customer_discount;
                $discSign = $disc === null ? null : bccomp((string) $disc, '0', 2);
            @endphp

            <div class="overflow-hidden rounded-2xl bg-white shadow-[0_8px_30px_-14px_rgba(79,70,229,0.18)] ring-1 ring-slate-100">
                <div class="flex items-start justify-between gap-3 border-b border-slate-100 px-6 py-5">
                    <div class="flex items-start gap-3">
                        <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-violet-50 text-violet-600" aria-hidden="true"><x-icon name="users" class="h-5 w-5" /></span>
                        <div>
                            <h3 class="text-base font-bold text-slate-900">Negosiasi Harga Customer</h3>
                        </div>
                    </div>
                    <span class="shrink-0 rounded-full bg-indigo-50 px-2.5 py-1 text-xs font-semibold text-indigo-700">Mode: {{ $opt::PRICING_MODES[$pricing->pricing_mode] ?? $pricing->pricing_mode }}</span>
                </div>

                <div class="p-6 space-y-6">
                    <!-- Ringkasan Harga -->
                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                        <div class="rounded-2xl bg-gradient-to-br from-indigo-50 to-violet-50 p-5 ring-1 ring-indigo-100">
                            <p class="mb-1 text-[11px] font-semibold uppercase tracking-[0.1em] text-indigo-600">Harga Rekomendasi</p>
                            @if ($recPerPax !== null)
                                <p class="text-2xl font-extrabold tracking-tight tabular-nums text-indigo-900">{{ $rp($recPerPax) }} <span class="text-sm font-medium">per pax</span></p>
                                <p class="mt-1 text-xs text-indigo-700">Total {{ $rp($pricing->recommended_price) }} untuk {{ $pax }} pax</p>
                            @else
                                <p class="text-2xl font-extrabold tracking-tight tabular-nums text-indigo-900">{{ $rp($pricing->recommended_price) }}</p>
                            @endif
                            <p class="mt-2 text-xs text-indigo-700 leading-relaxed">
                                Dari biaya negosiasi {{ $rp($neg['total']) }} dengan {{ $opt::PRICING_METHODS[$pricing->pricing_method] ?? $pricing->pricing_method }} {{ $pct($pricing->rate_percent) }}.
                            </p>
                        </div>

                        <div class="rounded-2xl bg-slate-50 p-5 ring-1 ring-slate-100">
                            <p class="mb-1 text-[11px] font-semibold uppercase tracking-[0.1em] text-slate-500">Harga Final Tersimpan</p>
                            @if ($pricing->final_price === null)
                                <p class="text-2xl font-extrabold tracking-tight text-slate-400">-</p>
                            @elseif ($perPaxMode && $pricing->price_per_pax !== null)
                                <p class="text-2xl font-extrabold tracking-tight tabular-nums text-slate-900">{{ $rp($pricing->price_per_pax) }} <span class="text-sm font-medium">per pax</span></p>
                                <p class="mt-1 text-xs text-slate-500">Total {{ $rp($pricing->final_price) }} untuk {{ $pax }} pax</p>
                            @else
                                <p class="text-2xl font-extrabold tracking-tight tabular-nums text-slate-900">{{ $rp($pricing->final_price) }}</p>
                            @endif
                        </div>

                        <div class="rounded-2xl p-5 ring-1 {{ $discSign !== null && $discSign < 0 ? 'bg-emerald-50 ring-emerald-100' : 'bg-slate-50 ring-slate-100' }}">
                            @if ($discSign === null)
                                <p class="mb-1 text-[11px] font-semibold uppercase tracking-[0.1em] text-slate-500">Selisih dengan rekomendasi</p>
                                <p class="text-2xl font-extrabold tracking-tight text-slate-400">-</p>
                            @elseif ($discSign > 0)
                                <p class="mb-1 text-[11px] font-semibold uppercase tracking-[0.1em] text-slate-500">Diskon untuk customer</p>
                                <p class="text-2xl font-extrabold tracking-tight tabular-nums text-slate-900">{{ $rp($disc) }}</p>
                                <p class="mt-1 text-xs text-slate-500">Harga final lebih rendah dari rekomendasi.</p>
                            @elseif ($discSign < 0)
                                <p class="mb-1 text-[11px] font-semibold uppercase tracking-[0.1em] text-emerald-700">Di atas harga rekomendasi</p>
                                <p class="text-2xl font-extrabold tracking-tight tabular-nums text-emerald-900">+{{ $rp($calc->absoluteMoney((string) $disc)) }}</p>
                                <p class="mt-1 text-xs text-emerald-700">Tidak ada diskon.</p>
                            @else
                                <p class="mb-1 text-[11px] font-semibold uppercase tracking-[0.1em] text-slate-500">Selisih dengan rekomendasi</p>
                                <p class="text-2xl font-extrabold tracking-tight tabular-nums text-slate-900">Rp 0</p>
                                <p class="mt-1 text-xs text-slate-500">Sama dengan rekomendasi.</p>
                            @endif
                        </div>
                    </div>

                    <form wire:submit="saveNegotiation" class="space-y-5 border-t border-slate-100 pt-5">
                        <div class="grid grid-cols-1 sm:grid-cols-3 gap-5">
                            <div>
                                <label class="mb-1.5 block text-xs font-semibold text-slate-700">Harga ditawarkan ({{ $perPaxMode ? 'per pax' : 'total' }})</label>
                                <x-money-input model="price.quoted" :disabled="! $priceEditable" class="w-full" input-class="w-full rounded-xl border border-slate-200 bg-slate-50 py-2.5 pl-10 pr-3 text-sm text-slate-900 placeholder:text-slate-400 transition-colors focus:border-indigo-500 focus:bg-white focus:ring-2 focus:ring-indigo-500/20 disabled:opacity-60" />
                                <p class="mt-1.5 text-xs text-slate-500">{{ $totalHint($price['quoted'] ?? '') }}</p>
                                @error('price.quoted')<p class="mt-1.5 text-xs text-red-600">{{ $message }}</p>@enderror
                            </div>
                            <div>
                                <label class="mb-1.5 block text-xs font-semibold text-slate-700">Harga yang diminta ({{ $perPaxMode ? 'per pax' : 'total' }})</label>
                                <x-money-input model="price.requested" :disabled="! $priceEditable" class="w-full" input-class="w-full rounded-xl border border-slate-200 bg-slate-50 py-2.5 pl-10 pr-3 text-sm text-slate-900 placeholder:text-slate-400 transition-colors focus:border-indigo-500 focus:bg-white focus:ring-2 focus:ring-indigo-500/20 disabled:opacity-60" />
                                <p class="mt-1.5 text-xs text-slate-500">{{ $totalHint($price['requested'] ?? '') }}</p>
                                @error('price.requested')<p class="mt-1.5 text-xs text-red-600">{{ $message }}</p>@enderror
                            </div>
                            <div>
                                <label class="mb-1.5 block text-xs font-semibold text-slate-700">Harga final disepakati ({{ $perPaxMode ? 'per pax' : 'total' }})</label>
                                <x-money-input model="price.final" :live="true" :disabled="! $priceEditable" class="w-full" input-class="w-full rounded-xl border border-slate-200 bg-slate-50 py-2.5 pl-10 pr-3 text-sm text-slate-900 placeholder:text-slate-400 transition-colors focus:border-indigo-500 focus:bg-white focus:ring-2 focus:ring-indigo-500/20 disabled:opacity-60" />
                                <p class="mt-1.5 text-xs text-slate-500">{{ $totalHint($price['final'] ?? '') }}</p>
                                @error('price.final')<p class="mt-1.5 text-xs text-red-600">{{ $message }}</p>@enderror
                            </div>
                        </div>

                        @if ($pricing->final_price !== null && $priceEditable)
                            <div>
                                <label class="mb-1.5 block text-xs font-semibold text-slate-700">Alasan bila mengubah harga final</label>
                                <input type="text" wire:model="price.change_reason" placeholder="Contoh: negosiasi dengan customer" class="w-full rounded-xl border border-slate-200 bg-slate-50 px-3.5 py-2.5 text-sm text-slate-900 placeholder:text-slate-400 transition-colors focus:border-indigo-500 focus:bg-white focus:ring-2 focus:ring-indigo-500/20 disabled:opacity-60">
                                @error('price.change_reason')<p class="mt-1.5 text-xs text-red-600">{{ $message }}</p>@enderror
                            </div>
                        @endif

                        @if ($evalFinal && $evalFinal['below_min'])
                            <div class="space-y-3 rounded-2xl bg-red-50 p-5 text-sm text-red-800 ring-1 ring-red-100">
                                <div class="flex items-start gap-3">
                                    <svg class="w-5 h-5 text-red-600 shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                                    <div>
                                        <p class="font-semibold text-red-900">Harga di bawah batas minimum</p>
                                        <p class="mt-1">Margin {{ $pct($evalFinal['margin'] ?? 0) }} lebih rendah dari batas minimum {{ $pct($pricing->min_margin_threshold) }}.</p>
                                        @if ($pricing->block_below_min)
                                            <p class="mt-1 font-medium">Aturan harga memblokir konfirmasi. Naikkan harga final.</p>
                                        @elseif ($pricing->require_approval)
                                            <p class="mt-1 font-medium">Butuh persetujuan Owner. Status: {{ $opt::APPROVAL_STATUS[$pricing->approval_status] ?? $pricing->approval_status }}.</p>
                                        @else
                                            <p class="mt-1">Isi alasan di bawah dan simpan untuk melanjutkan.</p>
                                        @endif
                                    </div>
                                </div>
                                @if (! $pricing->block_below_min)
                                    <div>
                                        <label class="mb-1.5 block text-xs font-semibold text-red-900">Alasan harga di bawah batas</label>
                                        <textarea wire:model="price.below_min_reason" rows="2" @disabled(! $priceEditable) class="w-full rounded-xl border border-red-200 bg-white px-3 py-2 text-sm text-slate-900 focus:border-red-500 focus:ring-2 focus:ring-red-500/20 disabled:bg-slate-50"></textarea>
                                        @error('price.below_min_reason')<p class="mt-1.5 text-xs text-red-600">{{ $message }}</p>@enderror
                                    </div>
                                @endif
                            </div>
                        @endif

                        @if ($showProfit && $evalFinal)
                            <div class="rounded-2xl border border-dashed border-amber-300 bg-amber-50 p-5">
                                <p class="mb-3 text-[11px] font-bold uppercase tracking-[0.1em] text-amber-700">Estimasi (bukan Actual Profit)</p>
                                <div class="grid grid-cols-2 sm:grid-cols-4 gap-4 text-sm">
                                    <div>
                                        <p class="text-amber-800 mb-0.5">Total harga (pratinjau)</p>
                                        <p class="font-semibold text-slate-900">{{ $rp($calc->calculateTotalPrice((string) $evalFinal['cost'], (string) $evalFinal['profit'])) }}</p>
                                    </div>
                                    <div>
                                        <p class="text-amber-800 mb-0.5">Estimated Profit</p>
                                        <p class="font-semibold text-slate-900">{{ $rp($evalFinal['profit']) }}</p>
                                    </div>
                                    <div>
                                        <p class="text-amber-800 mb-0.5">Estimated Margin</p>
                                        <p class="font-semibold text-slate-900">{{ $pct($evalFinal['margin'] ?? 0) }}</p>
                                    </div>
                                    <div>
                                        <p class="text-amber-800 mb-0.5">Profit per Orang</p>
                                        <p class="font-semibold text-slate-900">{{ $evalFinal['profit_per_pax'] !== null ? $rp($evalFinal['profit_per_pax']) : '-' }}</p>
                                    </div>
                                </div>
                            </div>
                        @endif

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
                            <div>
                                <label class="mb-1.5 block text-xs font-semibold text-slate-700">Penawaran berlaku sampai</label>
                                <input type="date" wire:model.live="price.quote_valid_until" @disabled(! $priceEditable) class="w-full rounded-xl border border-slate-200 bg-slate-50 px-3.5 py-2.5 text-sm text-slate-900 placeholder:text-slate-400 transition-colors focus:border-indigo-500 focus:bg-white focus:ring-2 focus:ring-indigo-500/20 disabled:opacity-60">
                                @if (! empty($price['quote_valid_until']))
                                    <p class="mt-1.5 text-xs text-slate-500">{{ rescue(fn () => \Carbon\Carbon::parse($price['quote_valid_until'])->locale('id')->translatedFormat('l, j F Y'), '') }}</p>
                                @endif
                                @error('price.quote_valid_until')<p class="mt-1.5 text-xs text-red-600">{{ $message }}</p>@enderror
                            </div>
                            <div>
                                <label class="mb-1.5 block text-xs font-semibold text-slate-700">Syarat pembayaran</label>
                                <input type="text" wire:model="price.payment_terms" @disabled(! $priceEditable) placeholder="Contoh: DP 30%, pelunasan H-3" class="w-full rounded-xl border border-slate-200 bg-slate-50 px-3.5 py-2.5 text-sm text-slate-900 placeholder:text-slate-400 transition-colors focus:border-indigo-500 focus:bg-white focus:ring-2 focus:ring-indigo-500/20 disabled:opacity-60">
                                @error('price.payment_terms')<p class="mt-1.5 text-xs text-red-600">{{ $message }}</p>@enderror
                            </div>
                            <div class="sm:col-span-2">
                                <label class="mb-1.5 block text-xs font-semibold text-slate-700">Syarat dan ketentuan</label>
                                <textarea wire:model="price.terms" rows="3" @disabled(! $priceEditable) class="w-full rounded-xl border border-slate-200 bg-slate-50 px-3.5 py-3 text-sm text-slate-900 placeholder:text-slate-400 transition-colors focus:border-indigo-500 focus:bg-white focus:ring-2 focus:ring-indigo-500/20 disabled:opacity-60"></textarea>
                                @error('price.terms')<p class="mt-1.5 text-xs text-red-600">{{ $message }}</p>@enderror
                            </div>
                        </div>

                        @if ($priceEditable)
                            <div class="flex justify-end pt-2">
                                <button type="submit" class="inline-flex items-center justify-center gap-2 rounded-xl bg-indigo-600 px-5 py-2.5 text-sm font-semibold text-white shadow-md shadow-indigo-600/25 transition-colors hover:bg-indigo-700 focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500 focus-visible:ring-offset-2">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7H5a2 2 0 00-2 2v9a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-3m-1 4l-3 3m0 0l-3-3m3 3V4"></path></svg>
                                    Simpan Negosiasi
                                </button>
                            </div>
                        @endif
                    </form>

                    <!-- Persetujuan -->
                    @if ($trip->status === 'negotiation')
                        @if ($pricing->require_approval && ! $pricing->block_below_min && $evalSaved && $evalSaved['below_min'])
                            <div class="mt-6 space-y-4 rounded-2xl bg-slate-50 p-5 text-sm ring-1 ring-slate-100">
                                <div class="flex items-center justify-between">
                                    <p class="font-semibold text-slate-800">Persetujuan harga</p>
                                    <span class="inline-flex items-center rounded-full bg-white px-2.5 py-1 text-xs font-semibold text-slate-700 ring-1 ring-slate-200">
                                        {{ $opt::APPROVAL_STATUS[$pricing->approval_status] ?? $pricing->approval_status }}
                                    </span>
                                </div>
                                @if ($pricing->approval_status === 'approved' && $pricing->approver)
                                    <p class="text-xs font-medium text-emerald-600">Disetujui oleh: {{ $pricing->approver->name }}</p>
                                @endif

                                @if ($priceEditable && in_array($pricing->approval_status, ['none', 'rejected'], true))
                                    <button wire:click="requestApproval" class="inline-flex items-center justify-center gap-2 rounded-xl bg-white px-4 py-2.5 text-sm font-semibold text-slate-700 ring-1 ring-slate-200 transition-colors hover:bg-slate-50 focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500">
                                        <svg class="w-4 h-4 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                                        Minta Persetujuan Owner
                                    </button>
                                @endif

                                @if ($canApprove && $pricing->approval_status !== 'approved')
                                    <div class="flex flex-col sm:flex-row items-start sm:items-end gap-3 pt-2">
                                        <div class="flex-1 w-full">
                                            <label class="mb-1.5 block text-xs font-semibold text-slate-700">Catatan persetujuan</label>
                                            <input type="text" wire:model="approvalNote" class="w-full rounded-xl border border-slate-200 bg-slate-50 px-3.5 py-2 text-sm text-slate-900 placeholder:text-slate-400 transition-colors focus:border-indigo-500 focus:bg-white focus:ring-2 focus:ring-indigo-500/20 disabled:opacity-60">
                                            @error('approvalNote')<p class="mt-1.5 text-xs text-red-600">{{ $message }}</p>@enderror
                                        </div>
                                        <div class="flex gap-2 w-full sm:w-auto">
                                            <button wire:click="approve" class="flex-1 rounded-xl bg-emerald-600 px-4 py-2 text-sm font-semibold text-white shadow-sm transition-colors hover:bg-emerald-700 focus:outline-none focus-visible:ring-2 focus-visible:ring-emerald-500 sm:flex-none">Setujui</button>
                                            @if ($pricing->approval_status === 'pending')
                                                <button wire:click="reject" class="flex-1 rounded-xl bg-red-600 px-4 py-2 text-sm font-semibold text-white shadow-sm transition-colors hover:bg-red-700 focus:outline-none focus-visible:ring-2 focus-visible:ring-red-500 sm:flex-none">Tolak</button>
                                            @endif
                                        </div>
                                    </div>
                                @endif
                            </div>
                        @endif

                    @if ($priceEditable)
                        <div class="flex flex-wrap items-center justify-between gap-3 border-t pt-4">
                            <button wire:click="backToEstimated"
                                    wire:confirm="Trip kembali ke Estimated. Struktur dan biaya negosiasi bisa diubah lagi, dan harga rekomendasi dihitung ulang. Lanjutkan?"
                                    class="px-3 py-1.5 text-sm rounded-md border border-gray-300 text-gray-700 hover:bg-gray-50">
                                Kembali ke Estimated
                            </button>

                                <div class="w-full sm:w-auto flex flex-col items-center sm:items-end gap-1.5">
                                    <button wire:click="confirmPrice" wire:confirm="Harga final dikonfirmasi dan trip berstatus Confirmed. Lanjutkan?" class="inline-flex items-center justify-center gap-2 rounded-xl bg-gradient-to-r from-indigo-600 to-violet-600 px-5 py-2.5 text-sm font-semibold text-white shadow-lg shadow-indigo-600/25 transition hover:from-indigo-700 hover:to-violet-700 focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500 focus-visible:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-70 w-full sm:w-auto" @if($problem) disabled @endif>
                                        Konfirmasi Harga Final
                                    </button>
                                    @if ($problem)
                                        <p class="text-xs text-red-500">{{ $problem }}</p>
                                    @endif
                                </div>
                            </div>
                        @endif
                    @endif
                </div>
            </div>
        @endif
    </div>
</div>