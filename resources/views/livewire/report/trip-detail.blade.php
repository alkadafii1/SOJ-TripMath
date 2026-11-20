@php
    $rp = fn ($v) => ((float) $v < 0 ? '-' : '').'Rp '.number_format(abs((float) $v), 0, ',', '.');
    $signed = fn ($v) => ((float) $v > 0 ? '+' : ((float) $v < 0 ? '-' : '')).'Rp '.number_format(abs((float) $v), 0, ',', '.');
    $types = \App\Support\Options::PAYMENT_TYPES;
    $methods = \App\Support\Options::PAYMENT_METHODS;
    $parties = \App\Support\Options::PROFIT_PARTIES;
@endphp

<div class="py-8">
    <div class="max-w-5xl mx-auto sm:px-6 lg:px-8 space-y-6">

        <div class="flex flex-wrap items-start justify-between gap-3">
            <div>
                <a href="{{ route('reports') }}" wire:navigate class="text-sm text-indigo-600 hover:underline print:hidden">&larr; Kembali ke laporan</a>
                <h2 class="mt-1 text-xl font-semibold text-gray-800">Detail keuangan trip</h2>
                <p class="text-sm text-gray-500">
                    {{ $trip->trip_code }} &middot; {{ $trip->trip_name }} &middot; {{ $trip->customer_name }}
                    &middot; {{ $trip->start_date->format('d/m/Y') }} - {{ $trip->end_date->format('d/m/Y') }} &middot; {{ $trip->effectivePax() }} pax
                </p>
            </div>
            <button type="button" onclick="window.print()"
                    class="px-3 py-1.5 text-sm rounded-md border border-gray-300 text-gray-700 hover:bg-gray-50 print:hidden">Cetak / simpan PDF</button>
        </div>

        @if (! $snapshot)
            <div class="rounded-md border border-amber-300 bg-amber-50 p-4 text-sm text-amber-900">
                Trip ini belum direkonsiliasi (status {{ \App\Models\Trip::STATUS[$trip->status] ?? $trip->status }}), jadi belum punya angka keuangan yang final.
            </div>
        @else
            <p class="text-xs text-gray-500">
                Snapshot dibekukan {{ $snapshot->reconciled_at->format('d/m/Y H:i') }} oleh {{ $snapshot->reconciler?->name ?? '-' }}.
            </p>

            <dl class="grid grid-cols-2 lg:grid-cols-4 gap-4 text-sm">
                @foreach ([
                    ['Revenue', $rp($snapshot->revenue)],
                    ['Standard Cost', $rp($snapshot->standard_cost)],
                    ['Negotiated Cost', $rp($snapshot->negotiated_cost)],
                    ['Actual Cost', $rp($snapshot->actual_cost)],
                    ['Vendor Saving', $signed($snapshot->vendor_saving)],
                    ['Customer Discount', $snapshot->customer_discount !== null ? $signed($snapshot->customer_discount) : '-'],
                    ['Actual Profit', $signed($snapshot->actual_profit)],
                    ['Sisa tagihan saat rekonsiliasi', $rp($snapshot->outstanding_at_reconcile)],
                ] as [$label, $value])
                    <div class="rounded-lg bg-white shadow p-4">
                        <dt class="text-gray-500">{{ $label }}</dt>
                        <dd class="mt-1 font-semibold">{{ $value }}</dd>
                    </div>
                @endforeach
            </dl>

            @if ($snapshot->reconcile_note)
                <p class="text-sm text-gray-600"><span class="font-medium">Alasan rekonsiliasi:</span> {{ $snapshot->reconcile_note }}</p>
            @endif

            @if ($snapshot->marketing_allocation !== null)
                <x-profit-waterfall title="Pembagian laba" :w="[
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

            {{-- Biaya per komponen --}}
            <div class="bg-white shadow sm:rounded-lg p-6 space-y-3">
                <h3 class="text-lg font-semibold text-gray-800">Biaya per komponen</h3>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 text-sm">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-3 py-2 text-left font-medium text-gray-600">Komponen</th>
                                <th class="px-3 py-2 text-right font-medium text-gray-600">Rencana (negosiasi)</th>
                                <th class="px-3 py-2 text-right font-medium text-gray-600">Aktual</th>
                                <th class="px-3 py-2 text-right font-medium text-gray-600">Selisih</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            @foreach ($items as $item)
                                @php
                                    $line = $figures['lines'][$item->id] ?? null;
                                @endphp
                                <tr>
                                    <td class="px-3 py-2">
                                        {{ $item->item_name }}
                                        @if ($item->is_additional)<span class="ml-1 rounded bg-sky-100 px-1.5 py-0.5 text-xs text-sky-800">tambahan</span>@endif
                                        <div class="text-xs text-gray-500">{{ $item->category }}</div>
                                    </td>
                                    <td class="px-3 py-2 text-right">{{ $item->is_additional ? '-' : $rp($line['planned'] ?? 0) }}</td>
                                    <td class="px-3 py-2 text-right">{{ $item->actual_cost !== null ? $rp($item->actual_cost) : '-' }}</td>
                                    <td class="px-3 py-2 text-right">{{ $line && $line['variance'] ? $signed($line['variance']['amount']) : '-' }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>

            {{-- Pembayaran --}}
            <div class="bg-white shadow sm:rounded-lg p-6 space-y-3">
                <h3 class="text-lg font-semibold text-gray-800">Pembayaran</h3>
                <table class="min-w-full divide-y divide-gray-200 text-sm">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-3 py-2 text-left font-medium text-gray-600">Tanggal</th>
                            <th class="px-3 py-2 text-left font-medium text-gray-600">Jenis</th>
                            <th class="px-3 py-2 text-left font-medium text-gray-600">Metode</th>
                            <th class="px-3 py-2 text-right font-medium text-gray-600">Jumlah</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @forelse ($payments as $p)
                            <tr class="{{ $p->voided_at ? 'text-gray-400 line-through' : '' }}">
                                <td class="px-3 py-2">{{ $p->paid_at->format('d/m/Y') }}</td>
                                <td class="px-3 py-2">{{ $types[$p->payment_type] ?? $p->payment_type }}@if ($p->installment_no) ke-{{ $p->installment_no }}@endif @if ($p->voided_at) (dibatalkan)@endif</td>
                                <td class="px-3 py-2">{{ $methods[$p->method] ?? $p->method }}</td>
                                <td class="px-3 py-2 text-right">{{ $rp($p->amount) }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="4" class="px-3 py-4 text-center text-gray-500">Tidak ada pembayaran.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if ($distributions->isNotEmpty())
                <div class="bg-white shadow sm:rounded-lg p-6 space-y-3">
                    <h3 class="text-lg font-semibold text-gray-800">Bagian laba tercatat</h3>
                    <table class="min-w-full divide-y divide-gray-200 text-sm">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-3 py-2 text-left font-medium text-gray-600">Pihak</th>
                                <th class="px-3 py-2 text-right font-medium text-gray-600">Persentase</th>
                                <th class="px-3 py-2 text-right font-medium text-gray-600">Jumlah</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            @foreach ($distributions as $d)
                                <tr>
                                    <td class="px-3 py-2">{{ $parties[$d->party] ?? $d->party }}</td>
                                    <td class="px-3 py-2 text-right">{{ \App\Support\Money::percent($d->percentage) }}</td>
                                    <td class="px-3 py-2 text-right">{{ $signed($d->amount) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        @endif
    </div>
</div>
