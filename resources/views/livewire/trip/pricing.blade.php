@php
    $rp = fn ($v) => \App\Support\Money::rp($v);
    $pct = fn ($v) => \App\Support\Money::percent($v);
    $opt = \App\Support\Options::class;
    $plain = fn ($v) => ($v === null || $v === '') ? '' : (str_contains((string) $v, '.') ? rtrim(rtrim((string) $v, '0'), '.') : (string) $v);
    $nice = fn ($v) => number_format((float) $v, 0, ',', '.');
    $std = $costs['standard'];
    $neg = $costs['negotiated'];
    $modeNow = $pricing?->pricing_mode ?? $mode;
    $perPaxMode = $modeNow === 'per_pax';
    $totalHint = fn ($v) => ($perPaxMode && preg_match('/^\d{1,13}(\.\d{1,2})?$/', trim((string) $v)))
        ? '= '.$rp(bcmul(trim((string) $v), (string) $pax, 2)).' untuk '.$pax.' pax'
        : '';
    $inPreStage = in_array($trip->status, ['draft', 'estimated'], true);
@endphp

<div class="py-8">
    <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">

        {{-- Judul --}}
        <div>
            <a href="{{ route('trips.show', $trip->id) }}" wire:navigate class="text-sm text-indigo-600 hover:underline">&larr; Kembali ke trip</a>
            <div class="mt-1 flex flex-wrap items-center justify-between gap-3">
                <div>
                    <h2 class="text-xl font-semibold text-gray-800">Harga &amp; negosiasi</h2>
                    <p class="text-sm text-gray-500">{{ $trip->trip_code }} &middot; {{ $trip->trip_name }} &middot; {{ $pax }} pax</p>
                </div>
                <span class="px-3 py-1 rounded-full text-sm {{ $trip->statusBadgeClass() }}">
                    {{ \App\Models\Trip::STATUS[$trip->status] ?? $trip->status }}
                </span>
            </div>
        </div>

        @if (session('status'))
            <div class="rounded-md bg-green-50 p-3 text-sm text-green-800">{{ session('status') }}</div>
        @endif
        @if (session('error'))
            <div class="rounded-md bg-red-50 p-3 text-sm text-red-800">{{ session('error') }}</div>
        @endif

        {{-- Ringkasan biaya --}}
        <dl class="grid grid-cols-2 sm:grid-cols-4 gap-4 text-sm">
            <div class="rounded-lg bg-white shadow p-4">
                <dt class="text-gray-500">Standard Cost</dt>
                <dd class="text-lg font-semibold">{{ $rp($std['total']) }}</dd>
            </div>
            <div class="rounded-lg bg-white shadow p-4">
                <dt class="text-gray-500">Negotiated Cost</dt>
                <dd class="text-lg font-semibold">{{ $rp($neg['total']) }}</dd>
            </div>
            <div class="rounded-lg bg-green-50 shadow p-4">
                <dt class="text-green-700">Vendor Saving</dt>
                <dd class="text-lg font-semibold text-green-900">{{ $rp($costs['saving']) }} <span class="text-sm font-normal">({{ $pct($costs['saving_percent']) }})</span></dd>
            </div>
            <div class="rounded-lg bg-white shadow p-4">
                <dt class="text-gray-500">Biaya per orang (negosiasi)</dt>
                <dd class="text-lg font-semibold">{{ $rp($neg['cost_per_person']) }}</dd>
            </div>
        </dl>

        {{-- Peringatan tarif Rp0 --}}
        @if (! empty($zeroCosts))
            <div class="rounded-md border border-amber-300 bg-amber-50 p-4 text-sm text-amber-900">
                <p class="font-medium">{{ count($zeroCosts) }} komponen masih bertarif Rp0</p>
                <p class="mt-1">
                    {{ implode(', ', array_slice($zeroCosts, 0, 5)) }}@if (count($zeroCosts) > 5), dan {{ count($zeroCosts) - 5 }} lainnya @endif
                </p>
                <p class="mt-1 text-amber-800">
                    Biaya dan margin di halaman ini bisa tidak akurat. Tarif disalin saat trip dibuat, jadi mengisi tarif di Data Master tidak mengubah trip ini.
                    Isi "Tarif negosiasi" di bawah, atau buat trip baru setelah tarif master terisi.
                </p>
            </div>
        @endif

        {{-- 1. Negosiasi vendor --}}
        <div class="bg-white shadow sm:rounded-lg p-6 space-y-4">
            <div>
                <h3 class="text-lg font-semibold text-gray-800">Negosiasi vendor</h3>
                <p class="text-sm text-gray-500">
                    Isi tarif hasil negosiasi per komponen. Kosong berarti sama dengan tarif standar. Data master tidak berubah.
                </p>
            </div>

            <form wire:submit="saveNegotiatedCosts" class="space-y-4">
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 text-sm">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-3 py-2 text-left font-medium text-gray-600">Komponen</th>
                                <th class="px-3 py-2 text-left font-medium text-gray-600">Tipe biaya</th>
                                <th class="px-3 py-2 text-right font-medium text-gray-600">Tarif standar</th>
                                <th class="px-3 py-2 text-right font-medium text-gray-600">Tarif negosiasi</th>
                                <th class="px-3 py-2 text-right font-medium text-gray-600">Subtotal standar</th>
                                <th class="px-3 py-2 text-right font-medium text-gray-600">Subtotal negosiasi</th>
                                <th class="px-3 py-2 text-right font-medium text-gray-600">Hemat</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            @forelse ($items as $item)
                                @php
                                    $sl = $std['lines'][$item->id]['amount'] ?? '0';
                                    $nl = $neg['lines'][$item->id]['amount'] ?? '0';
                                @endphp
                                <tr wire:key="neg-{{ $item->id }}">
                                    <td class="px-3 py-2">
                                        {{ $item->item_name }}
                                        <div class="text-xs text-gray-500">{{ $item->category }}</div>
                                    </td>
                                    <td class="px-3 py-2">{{ $opt::COST_TYPES[$item->cost_type] ?? $item->cost_type }}</td>
                                    <td class="px-3 py-2 text-right">
                                        {{ $item->cost_type === 'percentage' ? $plain($item->percentage).'%' : $rp($item->standard_cost) }}
                                    </td>
                                    <td class="px-3 py-2 text-right">
                                        @if ($item->cost_type === 'percentage')
                                            -
                                        @elseif ($costEditable)
                                            <div class="flex justify-end">
                                                <x-money-input model="negotiated.{{ $item->id }}" class="w-44"
                                                               placeholder="{{ $nice($item->standard_cost) }}" />
                                            </div>
                                            @error('negotiated.'.$item->id)<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                                        @else
                                            {{ $item->negotiated_cost !== null ? $rp($item->negotiated_cost) : 'sama dengan standar' }}
                                        @endif
                                    </td>
                                    <td class="px-3 py-2 text-right">{{ $rp($sl) }}</td>
                                    <td class="px-3 py-2 text-right">{{ $rp($nl) }}</td>
                                    <td class="px-3 py-2 text-right text-green-700">{{ $rp(bcsub($sl, $nl, 2)) }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="7" class="px-3 py-6 text-center text-gray-500">Belum ada komponen biaya pada trip ini.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                @if ($costEditable)
                    <div class="flex flex-wrap items-end gap-3">
                        <div class="flex-1 min-w-[16rem]">
                            <label class="block text-xs font-medium text-gray-600">Catatan negosiasi (opsional, masuk audit log)</label>
                            <input type="text" wire:model="negotiationNote"
                                   class="mt-1 w-full rounded-md border-gray-300 text-sm shadow-sm">
                        </div>
                        <button type="submit" class="px-4 py-2 text-sm rounded-md bg-indigo-600 text-white hover:bg-indigo-500">
                            Simpan biaya negosiasi
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

        {{-- 2. Harga rekomendasi (sebelum negosiasi dimulai) --}}
        @if ($inPreStage)
            <div class="bg-white shadow sm:rounded-lg p-6 space-y-4">
                <h3 class="text-lg font-semibold text-gray-800">Harga rekomendasi</h3>

                @if ($pricing)
                    <div class="rounded-md bg-gray-50 p-3 text-sm text-gray-600">
                        Harga sebelumnya: Recommended {{ $rp($pricing->recommended_price) }}@if ($pricing->final_price !== null), Final {{ $rp($pricing->final_price) }}@endif.
                        Harga rekomendasi dihitung ulang saat negosiasi dimulai lagi.
                    </div>
                @endif

                @if (! $preview)
                    <p class="text-sm text-amber-700">Belum ada aturan harga aktif. Minta Owner membuatnya di menu Aturan Harga.</p>
                @else
                    @php
                        $rule = $preview['rule'];
                    @endphp
                    <p class="text-sm text-gray-600">
                        Aturan aktif: <span class="font-medium">{{ $rule->method_label }} {{ $pct($rule->rate_percent) }}</span>,
                        {{ strtolower($rule->rounding_label) }},
                        batas margin minimum {{ $rule->min_margin_threshold !== null ? $pct($rule->min_margin_threshold) : 'tidak diatur' }}
                        @if ($rule->block_below_min) (diblokir bila di bawah batas)
                        @elseif ($rule->require_approval) (butuh persetujuan Owner bila di bawah batas)
                        @endif.
                    </p>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Mode harga</label>
                            <select wire:model.live="mode" class="mt-1 w-full rounded-md border-gray-300 text-sm shadow-sm">
                                @foreach ($opt::PRICING_MODES as $val => $label)
                                    @if ($val !== 'per_pax' || $trip->trip_type === 'open_trip')
                                        <option value="{{ $val }}">{{ $label }}</option>
                                    @endif
                                @endforeach
                            </select>
                        </div>
                        @if ($mode === 'package')
                            <div>
                                <label class="block text-sm font-medium text-gray-700">Harga paket total</label>
                                <x-money-input model="packagePrice" :live="true" class="mt-1" />
                                @error('packagePrice')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                            </div>
                        @endif
                    </div>

                    <dl class="grid grid-cols-1 sm:grid-cols-2 gap-4 text-sm">
                        <div class="rounded-md bg-indigo-50 p-4">
                            <dt class="text-indigo-700">Harga rekomendasi</dt>
                            @if ($mode === 'per_pax' && $preview['rec']['per_pax'] !== null)
                                <dd class="text-2xl font-semibold text-indigo-900">{{ $rp($preview['rec']['per_pax']) }} <span class="text-sm font-normal">per pax</span></dd>
                                <dd class="text-xs text-indigo-700">Total {{ $preview['recommended'] !== null ? $rp($preview['recommended']) : '-' }} untuk {{ $pax }} pax</dd>
                            @else
                                <dd class="text-2xl font-semibold text-indigo-900">{{ $preview['recommended'] !== null ? $rp($preview['recommended']) : '-' }}</dd>
                            @endif
                            <dd class="mt-1 text-xs text-indigo-700">
                                Dari biaya negosiasi {{ $rp($preview['rec']['cost']) }} dengan {{ $rule->method_label }} {{ $pct($rule->rate_percent) }}
                            </dd>
                        </div>
                        @if ($mode === 'package')
                            <div class="rounded-md bg-gray-50 p-4">
                                <dt class="text-gray-500">Saran mesin (biaya + margin)</dt>
                                <dd class="font-semibold">{{ $rp($preview['rec']['engine_total']) }}</dd>
                            </div>
                        @endif
                    </dl>

                    @if ($preview['eval'] && $preview['eval']['below_min'])
                        <div class="rounded-md bg-red-50 border border-red-200 p-3 text-sm text-red-800">
                            Margin pada harga ini {{ $pct($preview['eval']['margin'] ?? 0) }}, di bawah batas minimum {{ $pct($rule->min_margin_threshold) }}.
                        </div>
                    @endif

                    @if ($showProfit && $preview['eval'])
                        <div class="rounded-md border-2 border-dashed border-amber-400 bg-amber-50 p-4">
                            <p class="text-xs font-semibold uppercase tracking-wide text-amber-700">Estimasi (bukan Actual Profit)</p>
                            <dl class="mt-2 grid grid-cols-2 sm:grid-cols-3 gap-4 text-sm">
                                <div><dt class="text-amber-800">Estimated Profit</dt><dd class="font-semibold">{{ $rp($preview['eval']['profit']) }}</dd></div>
                                <div><dt class="text-amber-800">Estimated Margin</dt><dd class="font-semibold">{{ $pct($preview['eval']['margin'] ?? 0) }}</dd></div>
                                <div><dt class="text-amber-800">Profit per orang</dt><dd class="font-semibold">{{ $preview['eval']['profit_per_pax'] !== null ? $rp($preview['eval']['profit_per_pax']) : '-' }}</dd></div>
                            </dl>
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
                $recPerPax = ($perPaxMode && $pax > 0) ? bcdiv((string) $pricing->recommended_price, (string) $pax, 2) : null;
                $disc = $pricing->customer_discount;
                $discSign = $disc === null ? null : bccomp((string) $disc, '0', 2);
            @endphp

            <div class="bg-white shadow sm:rounded-lg p-6 space-y-5">
                <div class="flex flex-wrap items-center justify-between gap-2">
                    <h3 class="text-lg font-semibold text-gray-800">Negosiasi harga customer</h3>
                    <span class="text-xs text-gray-500">Mode: {{ $opt::PRICING_MODES[$pricing->pricing_mode] ?? $pricing->pricing_mode }}</span>
                </div>

                <dl class="grid grid-cols-1 sm:grid-cols-3 gap-4 text-sm">
                    <div class="rounded-md bg-indigo-50 p-4">
                        <dt class="text-indigo-700">Harga rekomendasi</dt>
                        @if ($recPerPax !== null)
                            <dd class="text-2xl font-semibold text-indigo-900">{{ $rp($recPerPax) }} <span class="text-sm font-normal">per pax</span></dd>
                            <dd class="text-xs text-indigo-700">Total {{ $rp($pricing->recommended_price) }} untuk {{ $pax }} pax</dd>
                        @else
                            <dd class="text-2xl font-semibold text-indigo-900">{{ $rp($pricing->recommended_price) }}</dd>
                        @endif
                        <dd class="mt-1 text-xs text-indigo-700">
                            Dari biaya negosiasi {{ $rp($neg['total']) }} dengan
                            {{ $opt::PRICING_METHODS[$pricing->pricing_method] ?? $pricing->pricing_method }} {{ $pct($pricing->rate_percent) }}.
                            Tidak berubah oleh harga final.
                        </dd>
                    </div>

                    <div class="rounded-md bg-gray-50 p-4">
                        <dt class="text-gray-500">Harga final tersimpan</dt>
                        @if ($pricing->final_price === null)
                            <dd class="text-xl font-semibold">-</dd>
                        @elseif ($perPaxMode && $pricing->price_per_pax !== null)
                            <dd class="text-xl font-semibold">{{ $rp($pricing->price_per_pax) }} <span class="text-sm font-normal">per pax</span></dd>
                            <dd class="text-xs text-gray-500">Total {{ $rp($pricing->final_price) }} untuk {{ $pax }} pax</dd>
                        @else
                            <dd class="text-xl font-semibold">{{ $rp($pricing->final_price) }}</dd>
                        @endif
                    </div>

                    <div class="rounded-md p-4 {{ $discSign !== null && $discSign < 0 ? 'bg-green-50' : 'bg-gray-50' }}">
                        @if ($discSign === null)
                            <dt class="text-gray-500">Selisih dengan rekomendasi</dt>
                            <dd class="text-xl font-semibold">-</dd>
                        @elseif ($discSign > 0)
                            <dt class="text-gray-500">Diskon untuk customer</dt>
                            <dd class="text-xl font-semibold">{{ $rp($disc) }}</dd>
                            <dd class="text-xs text-gray-500">Harga final lebih rendah dari rekomendasi.</dd>
                        @elseif ($discSign < 0)
                            <dt class="text-green-700">Di atas harga rekomendasi</dt>
                            <dd class="text-xl font-semibold text-green-900">+{{ $rp(bcmul((string) $disc, '-1', 2)) }}</dd>
                            <dd class="text-xs text-green-700">Tidak ada diskon.</dd>
                        @else
                            <dt class="text-gray-500">Selisih dengan rekomendasi</dt>
                            <dd class="text-xl font-semibold">Rp 0</dd>
                            <dd class="text-xs text-gray-500">Sama dengan rekomendasi.</dd>
                        @endif
                    </div>
                </dl>

                <form wire:submit="saveNegotiation" class="space-y-4">
                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Harga ditawarkan ({{ $perPaxMode ? 'per pax' : 'total' }})</label>
                            <x-money-input model="price.quoted" :disabled="! $priceEditable" class="mt-1" />
                            <p class="mt-1 text-xs text-gray-500">{{ $totalHint($price['quoted'] ?? '') }}</p>
                            @error('price.quoted')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Harga yang diminta customer ({{ $perPaxMode ? 'per pax' : 'total' }})</label>
                            <x-money-input model="price.requested" :disabled="! $priceEditable" class="mt-1" />
                            <p class="mt-1 text-xs text-gray-500">{{ $totalHint($price['requested'] ?? '') }}</p>
                            @error('price.requested')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Harga final yang disepakati ({{ $perPaxMode ? 'per pax' : 'total' }})</label>
                            <x-money-input model="price.final" :live="true" :disabled="! $priceEditable" class="mt-1" />
                            <p class="mt-1 text-xs text-gray-500">{{ $totalHint($price['final'] ?? '') }}</p>
                            @error('price.final')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                        </div>
                    </div>

                    @if ($pricing->final_price !== null && $priceEditable)
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Alasan bila mengubah harga final</label>
                            <input type="text" wire:model="price.change_reason" placeholder="Contoh: negosiasi dengan customer"
                                   class="mt-1 w-full rounded-md border-gray-300 text-sm shadow-sm">
                            @error('price.change_reason')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                        </div>
                    @endif

                    {{-- Peringatan di bawah batas minimum --}}
                    @if ($evalFinal && $evalFinal['below_min'])
                        <div class="rounded-md bg-red-50 border border-red-200 p-4 text-sm text-red-800 space-y-2">
                            <p class="font-medium">Harga di bawah batas minimum</p>
                            <p>Margin {{ $pct($evalFinal['margin'] ?? 0) }} lebih rendah dari batas minimum {{ $pct($pricing->min_margin_threshold) }}.</p>

                            @if ($pricing->block_below_min)
                                <p>Aturan harga memblokir konfirmasi. Naikkan harga final.</p>
                            @elseif ($pricing->require_approval)
                                <p>Butuh persetujuan Owner. Status: <span class="font-medium">{{ $opt::APPROVAL_STATUS[$pricing->approval_status] ?? $pricing->approval_status }}</span>.</p>
                            @else
                                <p>Isi alasan di bawah dan simpan untuk melanjutkan.</p>
                            @endif

                            @if (! $pricing->block_below_min)
                                <div>
                                    <label class="block text-xs font-medium text-red-900">Alasan harga di bawah batas</label>
                                    <textarea wire:model="price.below_min_reason" rows="2" @disabled(! $priceEditable)
                                              class="mt-1 w-full rounded-md border-red-200 text-sm shadow-sm disabled:bg-gray-100"></textarea>
                                    @error('price.below_min_reason')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                                </div>
                            @endif
                        </div>
                    @endif

                    {{-- Estimasi profit (khusus yang berhak melihat keuangan) --}}
                    @if ($showProfit && $evalFinal)
                        <div class="rounded-md border-2 border-dashed border-amber-400 bg-amber-50 p-4">
                            <p class="text-xs font-semibold uppercase tracking-wide text-amber-700">Estimasi (bukan Actual Profit)</p>
                            <dl class="mt-2 grid grid-cols-2 sm:grid-cols-4 gap-4 text-sm">
                                <div><dt class="text-amber-800">Total harga (pratinjau)</dt><dd class="font-semibold">{{ $rp(bcadd((string) $evalFinal['cost'], (string) $evalFinal['profit'], 2)) }}</dd></div>
                                <div><dt class="text-amber-800">Estimated Profit</dt><dd class="font-semibold">{{ $rp($evalFinal['profit']) }}</dd></div>
                                <div><dt class="text-amber-800">Estimated Margin</dt><dd class="font-semibold">{{ $pct($evalFinal['margin'] ?? 0) }}</dd></div>
                                <div><dt class="text-amber-800">Profit per orang</dt><dd class="font-semibold">{{ $evalFinal['profit_per_pax'] !== null ? $rp($evalFinal['profit_per_pax']) : '-' }}</dd></div>
                            </dl>
                        </div>
                    @endif

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Penawaran berlaku sampai</label>
                            <input type="date" wire:model.live="price.quote_valid_until" @disabled(! $priceEditable)
                                   class="mt-1 w-full rounded-md border-gray-300 text-sm shadow-sm disabled:bg-gray-100">
                            @if (! empty($price['quote_valid_until']))
                                <p class="mt-1 text-xs text-gray-500">
                                    {{ rescue(fn () => \Carbon\Carbon::parse($price['quote_valid_until'])->locale('id')->translatedFormat('l, j F Y'), '') }}
                                </p>
                            @endif
                            @error('price.quote_valid_until')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Syarat pembayaran</label>
                            <input type="text" wire:model="price.payment_terms" @disabled(! $priceEditable)
                                   placeholder="Contoh: DP 30%, pelunasan H-3"
                                   class="mt-1 w-full rounded-md border-gray-300 text-sm shadow-sm disabled:bg-gray-100">
                            @error('price.payment_terms')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                        </div>
                        <div class="sm:col-span-2">
                            <label class="block text-sm font-medium text-gray-700">Syarat dan ketentuan</label>
                            <textarea wire:model="price.terms" rows="3" @disabled(! $priceEditable)
                                      class="mt-1 w-full rounded-md border-gray-300 text-sm shadow-sm disabled:bg-gray-100"></textarea>
                            @error('price.terms')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                        </div>
                    </div>

                    @if ($priceEditable)
                        <div class="flex justify-end">
                            <button type="submit" class="px-4 py-2 text-sm rounded-md bg-indigo-600 text-white hover:bg-indigo-500">Simpan negosiasi</button>
                        </div>
                    @endif
                </form>

                {{-- Persetujuan --}}
                @if ($trip->status === 'negotiation')
                    @if ($pricing->require_approval && ! $pricing->block_below_min && $evalSaved && $evalSaved['below_min'])
                        <div class="rounded-md border border-gray-200 p-4 space-y-3 text-sm">
                            <p class="font-medium text-gray-800">
                                Persetujuan harga:
                                {{ $opt::APPROVAL_STATUS[$pricing->approval_status] ?? $pricing->approval_status }}
                                @if ($pricing->approval_status === 'approved' && $pricing->approver)
                                    oleh {{ $pricing->approver->name }}
                                @endif
                            </p>

                            @if ($priceEditable && in_array($pricing->approval_status, ['none', 'rejected'], true))
                                <button wire:click="requestApproval"
                                        class="px-3 py-1.5 text-sm rounded-md border border-gray-300 text-gray-700 hover:bg-gray-50">
                                    Minta persetujuan Owner
                                </button>
                            @endif

                            @if ($canApprove && $pricing->approval_status !== 'approved')
                                <div class="flex flex-wrap items-end gap-3">
                                    <div class="flex-1 min-w-[16rem]">
                                        <label class="block text-xs font-medium text-gray-600">Catatan persetujuan</label>
                                        <input type="text" wire:model="approvalNote" class="mt-1 w-full rounded-md border-gray-300 text-sm shadow-sm">
                                        @error('approvalNote')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                                    </div>
                                    <button wire:click="approve" class="px-3 py-2 text-sm rounded-md bg-green-600 text-white hover:bg-green-500">Setujui</button>
                                    @if ($pricing->approval_status === 'pending')
                                        <button wire:click="reject" class="px-3 py-2 text-sm rounded-md bg-red-600 text-white hover:bg-red-500">Tolak</button>
                                    @endif
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

                            <div class="text-right space-y-1">
                                <button wire:click="confirmPrice"
                                        wire:confirm="Harga final dikonfirmasi dan trip berstatus Confirmed. Lanjutkan?"
                                        class="px-4 py-2 text-sm font-medium rounded-md bg-emerald-600 text-white hover:bg-emerald-500">
                                    Konfirmasi harga final
                                </button>
                                @if ($problem)
                                    <p class="text-xs text-gray-500">Belum bisa dikonfirmasi: {{ $problem }}</p>
                                @endif
                            </div>
                        </div>
                    @endif
                @endif
            </div>
        @endif
    </div>
</div>