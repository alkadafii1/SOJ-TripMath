@props(['w', 'title' => 'Rincian profit'])

@php
    $money = fn ($v) => ((float) $v < 0 ? '-' : '').'Rp '.number_format(abs((float) $v), 0, ',', '.');
    $pct = fn ($v) => rtrim(rtrim(number_format((float) $v, 4, ',', '.'), '0'), ',').'%';
    $base = \App\Support\Options::PROFIT_BASES[$w['marketing_base']] ?? $w['marketing_base'];
    $rows = [
        ['Revenue (harga final)', $money($w['revenue']), false],
        ['Actual Cost', '- '.$money($w['actual_cost']), false],
        ['Actual Profit', $money($w['actual_profit']), true],
        ['Marketing allocation ('.$pct($w['marketing_percent']).' dari '.$base.')', '- '.$money($w['marketing_allocation']), false],
        ['Distributable Profit', $money($w['distributable']), true],
        ['Owner ('.$pct($w['owner_percent']).')', $money($w['owner_share']), false],
        ['Operational Partner ('.$pct($w['partner_percent']).')', $money($w['partner_share']), false],
    ];
@endphp

<div class="rounded-md border border-gray-200 bg-white">
    <p class="border-b border-gray-200 px-4 py-2 text-xs font-semibold uppercase tracking-wide text-gray-500">{{ $title }}</p>
    <dl class="divide-y divide-gray-100 text-sm">
        @foreach ($rows as [$label, $value, $bold])
            <div class="flex items-center justify-between gap-4 px-4 py-2 {{ $bold ? 'bg-gray-50 font-semibold' : '' }}">
                <dt class="text-gray-600 {{ $bold ? '!text-gray-800' : '' }}">{{ $label }}</dt>
                <dd class="whitespace-nowrap">{{ $value }}</dd>
            </div>
        @endforeach
    </dl>
    @if ((float) $w['actual_profit'] < 0)
        <p class="border-t border-amber-200 bg-amber-50 px-4 py-2 text-xs text-amber-800">
            Trip ini merugi. Kerugian dibagi dengan persentase yang sama, sehingga bagian Owner dan Partner bernilai negatif.
        </p>
    @endif
</div>
