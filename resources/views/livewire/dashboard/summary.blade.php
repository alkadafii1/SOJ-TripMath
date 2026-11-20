@php
    $rp = fn ($v) => ((float) $v < 0 ? '-' : '').'Rp '.number_format(abs((float) $v), 0, ',', '.');
    $cards = [
        ['Trip direkonsiliasi', number_format($s['trips'], 0, ',', '.'), 'bg-white'],
        ['Revenue', $rp($s['revenue']), 'bg-white'],
        ['Standard Cost', $rp($s['standard_cost']), 'bg-white'],
        ['Negotiated Cost', $rp($s['negotiated_cost']), 'bg-white'],
        ['Actual Cost', $rp($s['actual_cost']), 'bg-white'],
        ['Vendor Saving', $rp($s['vendor_saving']), 'bg-green-50'],
        ['Customer Discount', $rp($s['customer_discount']), 'bg-white'],
        ['Actual Profit', $rp($s['actual_profit']), 'bg-indigo-50'],
        ['Average Margin', $s['average_margin'] !== null ? \App\Support\Money::percent($s['average_margin']) : '-', 'bg-indigo-50'],
        ['Marketing Allocation', $rp($s['marketing_allocation']), 'bg-white'],
        ['Bagian Owner', $rp($s['owner_share']), 'bg-white'],
        ['Bagian Operational Partner', $rp($s['partner_share']), 'bg-white'],
    ];
@endphp

<div class="space-y-4">
    <div class="flex flex-wrap items-end justify-between gap-3">
        <div>
            <h3 class="text-lg font-semibold text-gray-800">Ringkasan keuangan</h3>
            <p class="text-xs text-gray-500">
                Periode {{ $period->label() }}. Dihitung dari trip yang selesai pada periode itu dan sudah direkonsiliasi.
            </p>
        </div>

        <div class="flex flex-wrap items-end gap-2">
            <select aria-label="Periode keuangan" wire:model.live="preset" class="rounded-md border-gray-300 text-sm shadow-sm">
                @foreach ($presets as $val => $label)
                    <option value="{{ $val }}">{{ $label }}</option>
                @endforeach
            </select>
            @if ($preset === 'custom')
                <input type="date" wire:model.live="from" class="rounded-md border-gray-300 text-sm shadow-sm">
                <input type="date" wire:model.live="to" class="rounded-md border-gray-300 text-sm shadow-sm">
            @endif
            @if ($canReport)
                <a href="{{ route('reports') }}" wire:navigate class="text-sm text-indigo-600 hover:underline">Lihat laporan</a>
            @endif
        </div>
    </div>

    @if ($error)
        <div class="rounded-md border border-amber-300 bg-amber-50 p-3 text-sm text-amber-900">{{ $error }}</div>
    @endif

    <p class="text-xs text-gray-500">Dasar perhitungan: <strong>{{ $s['trips'] }} trip direkonsiliasi</strong>. Periode keuangan diatur terpisah dari periode operasional.</p>
    <dl class="grid gap-4 sm:grid-cols-3">
        @foreach ([$cards[1], $cards[7], $cards[8]] as [$label, $value, $bg])
            <div class="rounded-xl border border-indigo-100 bg-indigo-50/50 p-5">
                <dt class="text-sm text-indigo-900">{{ $label }}</dt>
                <dd class="mt-2 text-2xl font-semibold text-gray-900">{{ $value }}</dd>
            </div>
        @endforeach
    </dl>
    <div class="grid gap-4 lg:grid-cols-3">
        @foreach (['Perbandingan Biaya' => [2, 3, 4], 'Penghematan & Diskon' => [5, 6], 'Pembagian Hasil' => [9, 10, 11]] as $title => $indices)
            <section class="rounded-xl border border-gray-200 bg-white p-5">
                <h4 class="text-sm font-semibold text-gray-800">{{ $title }}</h4>
                <dl class="mt-3 space-y-3 text-sm">
                    @foreach ($indices as $index)
                        <div class="flex flex-wrap justify-between gap-2">
                            <dt class="text-gray-500">{{ $cards[$index][0] }}</dt>
                            <dd class="font-medium text-gray-900">{{ $cards[$index][1] }}</dd>
                        </div>
                    @endforeach
                </dl>
            </section>
        @endforeach
    </div>

    @if ($s['trips_without_sharing'] > 0)
        <p class="text-xs text-amber-700">
            {{ $s['trips_without_sharing'] }} trip direkonsiliasi sebelum aturan bagi hasil tersedia, sehingga tidak ikut dijumlah di Marketing Allocation dan bagian Owner dan Partner.
        </p>
    @endif


</div>
