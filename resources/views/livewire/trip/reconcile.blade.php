@php
    $rp = fn ($v) => \App\Support\Money::rp($v);
    $signed = fn ($v) => \App\Support\Money::signed($v);
    $pct = fn ($v) => \App\Support\Money::percent($v);
    $tone = fn ($v) => (float) $v > 0 ? 'text-red-600' : ((float) $v < 0 ? 'text-green-700' : 'text-gray-500');
    $vn = $figures['variance_negotiated'];
    $vs = $figures['variance_standard'];
    $profit = $figures['profit'];
    $outstandingNow = $figures['outstanding'] !== null && (float) $figures['outstanding'] > 0;
@endphp

<div class="py-8">
    <div class="max-w-6xl mx-auto sm:px-6 lg:px-8 space-y-6">

        <div>
            <a href="{{ route('trips.show', $trip->id) }}" wire:navigate class="text-sm text-indigo-600 hover:underline">&larr; Kembali ke trip</a>
            <div class="mt-1 flex flex-wrap items-center justify-between gap-3">
                <div>
                    <h2 class="text-xl font-semibold text-gray-800">Biaya aktual &amp; rekonsiliasi</h2>
                    <p class="text-sm text-gray-500">{{ $trip->trip_code }} &middot; {{ $trip->trip_name }} &middot; {{ $trip->effectivePax() }} pax</p>
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

        @if (! $canEditActual && $trip->status !== 'reconciled' && $trip->status !== 'completed')
            <div class="rounded-md border border-amber-300 bg-amber-50 p-4 text-sm text-amber-900">
                Biaya aktual baru bisa diisi saat trip berstatus Ongoing atau Completed. Status saat ini:
                {{ \App\Models\Trip::STATUS[$trip->status] ?? $trip->status }}.
            </div>
        @endif

        {{-- Ringkasan biaya --}}
        <dl class="grid grid-cols-2 lg:grid-cols-5 gap-4 text-sm">
            <div class="rounded-lg bg-white shadow p-4">
                <dt class="text-gray-500">Standard Cost</dt>
                <dd class="text-lg font-semibold">{{ $rp($figures['standard']) }}</dd>
            </div>
            <div class="rounded-lg bg-white shadow p-4">
                <dt class="text-gray-500">Negotiated Cost</dt>
                <dd class="text-lg font-semibold">{{ $rp($figures['negotiated']) }}</dd>
            </div>
            <div class="rounded-lg bg-indigo-50 shadow p-4">
                <dt class="text-indigo-700">Actual Cost</dt>
                <dd class="text-lg font-semibold text-indigo-900">{{ $rp($figures['actual']) }}</dd>
            </div>
            <div class="rounded-lg bg-white shadow p-4">
                <dt class="text-gray-500">Selisih vs negosiasi</dt>
                <dd class="text-lg font-semibold {{ $tone($vn['amount']) }}">{{ $signed($vn['amount']) }}</dd>
                @if ($vn['percent'] !== null)
                    <dd class="text-xs text-gray-500">{{ $pct($vn['percent']) }}</dd>
                @endif
            </div>
            <div class="rounded-lg bg-white shadow p-4">
                <dt class="text-gray-500">Selisih vs standar</dt>
                <dd class="text-lg font-semibold {{ $tone($vs['amount']) }}">{{ $signed($vs['amount']) }}</dd>
                @if ($vs['percent'] !== null)
                    <dd class="text-xs text-gray-500">{{ $pct($vs['percent']) }}</dd>
                @endif
            </div>
        </dl>
        <p class="-mt-3 text-xs text-gray-500">Selisih positif berarti biaya sebenarnya lebih mahal dari rencana; negatif berarti lebih hemat.</p>

        {{-- Profit aktual (khusus izin keuangan) --}}
        @if ($showFinance && $profit)
            <div class="rounded-lg border-2 border-green-300 bg-green-50 p-4">
                <p class="text-xs font-semibold uppercase tracking-wide text-green-700">Actual Profit (Revenue dikurangi Actual Cost)</p>
                <dl class="mt-2 grid grid-cols-2 lg:grid-cols-5 gap-4 text-sm">
                    <div><dt class="text-green-800">Revenue (harga final)</dt><dd class="font-semibold">{{ $rp($figures['revenue']) }}</dd></div>
                    <div><dt class="text-green-800">Actual Profit</dt><dd class="font-semibold">{{ $signed($profit['profit']) }}</dd></div>
                    <div><dt class="text-green-800">Margin</dt><dd class="font-semibold">{{ $profit['margin'] !== null ? $pct($profit['margin']) : '-' }}</dd></div>
                    <div><dt class="text-green-800">Profit per orang</dt><dd class="font-semibold">{{ $profit['per_pax'] !== null ? $signed($profit['per_pax']) : '-' }}</dd></div>
                    <div>
                        <dt class="text-green-800">Sisa tagihan</dt>
                        <dd class="font-semibold {{ $outstandingNow ? 'text-red-600' : '' }}">{{ $figures['outstanding'] !== null ? $rp($figures['outstanding']) : '-' }}</dd>
                    </div>
                </dl>

                @if ($trip->status !== 'reconciled')
                    @if ($figures['split'])
                        @php
                            $sp = $figures['split'];
                            $wfPreview = [
                                'revenue' => $figures['revenue'],
                                'actual_cost' => $figures['actual'],
                                'actual_profit' => $profit['profit'],
                                'marketing_percent' => $sp['marketing_percent'],
                                'marketing_base' => $sp['marketing_base'],
                                'marketing_allocation' => $sp['marketing_allocation'],
                                'distributable' => $sp['distributable'],
                                'owner_percent' => $sp['shares']['owner']['percent'],
                                'owner_share' => $sp['shares']['owner']['amount'],
                                'partner_percent' => $sp['shares']['partner']['percent'],
                                'partner_share' => $sp['shares']['partner']['amount'],
                            ];
                        @endphp
                        <div class="mt-3">
                            <x-profit-waterfall :w="$wfPreview" title="Perkiraan pembagian laba (aturan yang berlaku hari ini)" />
                        </div>
                    @else
                        <p class="mt-2 text-xs text-amber-800">{{ $figures['rule_problem'] }}</p>
                    @endif
                @endif
            </div>
        @endif

        {{-- Tabel biaya aktual --}}
        <form wire:submit="saveActual" class="bg-white shadow sm:rounded-lg p-6 space-y-4">
            <div>
                <h3 class="text-lg font-semibold text-gray-800">Biaya aktual per komponen</h3>
                <p class="text-sm text-gray-500">
                    Isi total yang benar-benar dibayarkan untuk tiap komponen (bukan tarif satuan). Komponen wajib harus diisi, isi 0 bila tidak jadi dipakai.
                </p>
            </div>

            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200 text-sm">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-3 py-2 text-left font-medium text-gray-600">Komponen</th>
                            <th class="px-3 py-2 text-right font-medium text-gray-600">Rencana (negosiasi)</th>
                            <th class="px-3 py-2 text-right font-medium text-gray-600">Biaya aktual</th>
                            <th class="px-3 py-2 text-right font-medium text-gray-600">Selisih</th>
                            <th class="px-3 py-2"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @forelse ($items as $item)
                            @php
                                $line = $figures['lines'][$item->id] ?? null;
                                $isMissing = in_array($item->id, $figures['missing_ids'], true);
                            @endphp
                            <tr wire:key="act-{{ $item->id }}" class="{{ $isMissing ? 'bg-amber-50' : '' }}">
                                <td class="px-3 py-2">
                                    {{ $item->item_name }}
                                    @if ($item->is_additional)
                                        <span class="ml-1 rounded bg-sky-100 px-1.5 py-0.5 text-xs text-sky-800">tambahan</span>
                                    @elseif (! $item->is_required)
                                        <span class="ml-1 text-xs text-gray-400">(opsional)</span>
                                    @endif
                                    <div class="text-xs text-gray-500">{{ $item->category }}@if ($item->notes) &middot; {{ $item->notes }}@endif</div>
                                </td>
                                <td class="px-3 py-2 text-right">{{ $item->is_additional ? '-' : $rp($line['planned'] ?? 0) }}</td>
                                <td class="px-3 py-2 text-right">
                                    @if ($canEditActual)
                                        <div class="flex justify-end">
                                            <x-money-input model="actual.{{ $item->id }}" class="w-44" placeholder="{{ $isMissing ? 'wajib diisi' : '' }}" />
                                        </div>
                                        @error('actual.'.$item->id)<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                                    @else
                                        {{ $item->actual_cost !== null ? $rp($item->actual_cost) : '-' }}
                                    @endif
                                </td>
                                <td class="px-3 py-2 text-right {{ $line && $line['variance'] ? $tone($line['variance']['amount']) : 'text-gray-400' }}">
                                    {{ $line && $line['variance'] ? $signed($line['variance']['amount']) : '-' }}
                                </td>
                                <td class="px-3 py-2 text-right whitespace-nowrap">
                                    @if ($canEditActual && $item->is_additional)
                                        <button type="button" wire:click="startVoid({{ $item->id }})" class="text-red-600 hover:underline">Batalkan</button>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="px-3 py-6 text-center text-gray-500">Belum ada komponen biaya.</td></tr>
                        @endforelse
                    </tbody>
                    <tfoot class="bg-gray-50 font-semibold">
                        <tr>
                            <td class="px-3 py-2">Total</td>
                            <td class="px-3 py-2 text-right">{{ $rp($figures['negotiated']) }}</td>
                            <td class="px-3 py-2 text-right">{{ $rp($figures['actual']) }}</td>
                            <td class="px-3 py-2 text-right {{ $tone($vn['amount']) }}">{{ $signed($vn['amount']) }}</td>
                            <td></td>
                        </tr>
                    </tfoot>
                </table>
            </div>

            @if ($voidingId)
                <div class="rounded-md border border-red-200 bg-red-50 p-4 space-y-3">
                    <p class="text-sm text-red-800">Biaya tambahan yang dibatalkan tidak dihitung lagi. Alasan wajib diisi.</p>
                    <div>
                        <input type="text" wire:model="voidReason" placeholder="Alasan pembatalan"
                               class="w-full rounded-md border-red-200 text-sm shadow-sm">
                        @error('voidReason')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                    </div>
                    <div class="flex gap-3">
                        <button type="button" wire:click="confirmVoid" class="px-3 py-1.5 text-sm rounded-md bg-red-600 text-white hover:bg-red-500">Batalkan biaya tambahan</button>
                        <button type="button" wire:click="cancelVoid" class="px-3 py-1.5 text-sm rounded-md border border-gray-300 text-gray-700 hover:bg-white">Tutup</button>
                    </div>
                </div>
            @endif

            @if ($canEditActual)
                <div class="flex flex-wrap items-end gap-3">
                    <div class="flex-1 min-w-[16rem]">
                        <label class="block text-xs font-medium text-gray-600">Alasan perubahan (wajib bila mengubah biaya aktual yang sudah tercatat)</label>
                        <input type="text" wire:model="actualNote" class="mt-1 w-full rounded-md border-gray-300 text-sm shadow-sm">
                        @error('actualNote')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                    </div>
                    <button type="submit" class="px-4 py-2 text-sm rounded-md bg-indigo-600 text-white hover:bg-indigo-500">Simpan biaya aktual</button>
                </div>
            @endif
        </form>

        {{-- Biaya tambahan --}}
        @if ($canEditActual)
            <form wire:submit="addExtra" class="bg-white shadow sm:rounded-lg p-6 space-y-4">
                <div>
                    <h3 class="text-lg font-semibold text-gray-800">Tambah biaya di luar rencana</h3>
                    <p class="text-sm text-gray-500">Contoh: parkir tambahan, denda, atau biaya mendadak. Biaya ini ikut menambah Actual Cost.</p>
                </div>
                <div class="grid grid-cols-1 sm:grid-cols-4 gap-3">
                    <div>
                        <label class="block text-xs font-medium text-gray-600">Kategori</label>
                        <select wire:model="extra.category" class="mt-1 w-full rounded-md border-gray-300 text-sm shadow-sm">
                            <option value="">-- pilih --</option>
                            @foreach ($categories as $val => $label)
                                <option value="{{ $val }}">{{ $label }}</option>
                            @endforeach
                        </select>
                        @error('extra.category')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-600">Nama biaya</label>
                        <input type="text" wire:model="extra.name" class="mt-1 w-full rounded-md border-gray-300 text-sm shadow-sm">
                        @error('extra.name')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-600">Jumlah</label>
                        <x-money-input model="extra.amount" class="mt-1" />
                        @error('extra.amount')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-600">Catatan (opsional)</label>
                        <input type="text" wire:model="extra.notes" class="mt-1 w-full rounded-md border-gray-300 text-sm shadow-sm">
                        @error('extra.notes')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                    </div>
                </div>
                <div class="flex justify-end">
                    <button type="submit" class="px-4 py-2 text-sm rounded-md bg-gray-800 text-white hover:bg-gray-700">+ Tambah biaya</button>
                </div>
            </form>
        @endif

        {{-- Checklist dan rekonsiliasi --}}
        @if (in_array($trip->status, ['completed', 'reconciled'], true))
            <div class="bg-white shadow sm:rounded-lg p-6 space-y-4">
                <h3 class="text-lg font-semibold text-gray-800">Checklist rekonsiliasi</h3>

                <ul class="space-y-2 text-sm">
                    @foreach ($checklist as $check)
                        <li class="flex items-start gap-2">
                            <span class="mt-0.5 inline-flex h-5 w-5 shrink-0 items-center justify-center rounded-full text-xs font-bold {{ $check['ok'] ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700' }}">
                                {{ $check['ok'] ? 'v' : '!' }}
                            </span>
                            <span>
                                {{ $check['label'] }}
                                @if (! $check['ok'] && $check['detail'])
                                    <span class="block text-xs text-red-600">{{ $check['detail'] }}</span>
                                @endif
                            </span>
                        </li>
                    @endforeach
                </ul>

                @if ($trip->status === 'completed')
                    @if ($canReconcile)
                        <form wire:submit="reconcile" class="space-y-4 border-t pt-4">
                            @if ($outstandingNow)
                                <div class="rounded-md border border-amber-300 bg-amber-50 p-3 text-sm text-amber-900">
                                    Masih ada sisa tagihan {{ $rp($figures['outstanding']) }}. Rekonsiliasi tetap bisa dilakukan, tetapi alasan wajib diisi
                                    dan sisa tagihan akan dicatat di snapshot.
                                </div>
                            @endif

                            <label class="flex items-start gap-2 text-sm text-gray-700">
                                <input type="checkbox" wire:model="reviewed" class="mt-0.5 rounded border-gray-300 text-indigo-600 shadow-sm">
                                <span>Saya sudah memeriksa biaya tambahan (atau memang tidak ada biaya tambahan).</span>
                            </label>

                            <div>
                                <label class="block text-sm font-medium text-gray-700">
                                    Alasan rekonsiliasi {{ $outstandingNow ? '(wajib karena ada sisa tagihan)' : '(opsional)' }}
                                </label>
                                <textarea wire:model="note" rows="2" class="mt-1 w-full rounded-md border-gray-300 text-sm shadow-sm"></textarea>
                                @error('note')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                            </div>

                            <div class="flex items-center justify-between gap-3">
                                <p class="text-xs text-gray-500">Setelah direkonsiliasi, angka keuangan dibekukan dan trip tidak bisa diubah lagi.</p>
                                <button type="submit" @disabled(! $checklistOk)
                                        wire:confirm="Rekonsiliasi membekukan angka keuangan dan mengunci trip. Lanjutkan?"
                                        class="px-4 py-2 text-sm font-medium rounded-md bg-emerald-600 text-white hover:bg-emerald-500 disabled:cursor-not-allowed disabled:bg-gray-300">
                                    Rekonsiliasi dan kunci trip
                                </button>
                            </div>
                        </form>
                    @else
                        <p class="border-t pt-4 text-sm text-gray-500">Rekonsiliasi hanya dapat dilakukan oleh Owner.</p>
                    @endif
                @endif
            </div>
        @endif

        {{-- Snapshot --}}
        @if ($snapshot)
            <div class="bg-white shadow sm:rounded-lg p-6 space-y-3">
                <div class="flex flex-wrap items-center justify-between gap-2">
                    <h3 class="text-lg font-semibold text-gray-800">Snapshot keuangan</h3>
                    <span class="text-xs text-gray-500">
                        Dibekukan {{ $snapshot->reconciled_at->format('d/m/Y H:i') }} oleh {{ $snapshot->reconciler?->name ?? '-' }}
                    </span>
                </div>

                @if ($showFinance)
                    <dl class="grid grid-cols-2 lg:grid-cols-4 gap-4 text-sm">
                        <div><dt class="text-gray-500">Revenue</dt><dd class="font-semibold">{{ $rp($snapshot->revenue) }}</dd></div>
                        <div><dt class="text-gray-500">Standard Cost</dt><dd class="font-semibold">{{ $rp($snapshot->standard_cost) }}</dd></div>
                        <div><dt class="text-gray-500">Negotiated Cost</dt><dd class="font-semibold">{{ $rp($snapshot->negotiated_cost) }}</dd></div>
                        <div><dt class="text-gray-500">Actual Cost</dt><dd class="font-semibold">{{ $rp($snapshot->actual_cost) }}</dd></div>
                        <div><dt class="text-gray-500">Vendor Saving</dt><dd class="font-semibold">{{ $signed($snapshot->vendor_saving) }}</dd></div>
                        <div><dt class="text-gray-500">Customer Discount</dt><dd class="font-semibold">{{ $snapshot->customer_discount !== null ? $signed($snapshot->customer_discount) : '-' }}</dd></div>
                        <div><dt class="text-gray-500">Actual Profit</dt><dd class="font-semibold">{{ $signed($snapshot->actual_profit) }}</dd></div>
                        <div><dt class="text-gray-500">Sisa tagihan saat rekonsiliasi</dt><dd class="font-semibold">{{ $rp($snapshot->outstanding_at_reconcile) }}</dd></div>
                    </dl>

                    @if ($snapshot->marketing_allocation !== null)
                        <x-profit-waterfall title="Pembagian laba (dibekukan saat rekonsiliasi)" :w="[
                            'revenue' => $snapshot->revenue,
                            'actual_cost' => $snapshot->actual_cost,
                            'actual_profit' => $snapshot->actual_profit,
                            'marketing_percent' => $snapshot->marketing_percent,
                            'marketing_base' => $snapshot->marketing_base,
                            'marketing_allocation' => $snapshot->marketing_allocation,
                            'distributable' => $snapshot->distributable_profit,
                            'owner_percent' => $snapshot->owner_percent,
                            'owner_share' => $snapshot->owner_share,
                            'partner_percent' => $snapshot->partner_percent,
                            'partner_share' => $snapshot->partner_share,
                        ]" />
                    @else
                        <p class="text-sm text-gray-500">Trip ini direkonsiliasi sebelum aturan bagi hasil tersedia, jadi tidak punya pembagian laba.</p>
                    @endif

                    @if ($snapshot->reconcile_note)
                        <p class="text-sm text-gray-600"><span class="font-medium">Alasan:</span> {{ $snapshot->reconcile_note }}</p>
                    @endif
                @else
                    <p class="text-sm text-gray-500">Angka keuangan snapshot hanya terlihat oleh pengguna dengan izin keuangan.</p>
                @endif
            </div>
        @endif
    </div>
</div>