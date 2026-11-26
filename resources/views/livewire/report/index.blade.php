@php
    $rp = fn ($v) => ((float) $v < 0 ? '-' : '').'Rp '.number_format(abs((float) $v), 0, ',', '.');
    $signed = fn ($v) => ((float) $v > 0 ? '+' : ((float) $v < 0 ? '-' : '')).'Rp '.number_format(abs((float) $v), 0, ',', '.');
    $cell = function (string $type, $v) use ($rp, $signed) {
        if ($v === null || $v === '') {
            return '-';
        }

        return match ($type) {
            'money' => $rp($v),
            'signed' => $signed($v),
            'percent' => \App\Support\Money::percent($v),
            'int' => number_format((int) $v, 0, ',', '.'),
            'date' => $v->format('d/m/Y'),
            default => $v,
        };
    };
    $exportQuery = [
        'period' => $period->preset,
        'from' => $period->from->toDateString(),
        'to' => $period->to->toDateString(),
    ];
@endphp

<div class="py-8">
    <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-4">

        <div class="print:hidden">
            <h2 class="text-xl font-semibold text-gray-800">Laporan</h2>
            <p class="text-sm text-gray-500">Semua laporan memakai snapshot trip yang sudah direkonsiliasi dan dikelompokkan menurut tanggal selesai trip.</p>
        </div>

        {{-- Periode dan unduhan --}}
        <div class="flex flex-wrap items-end justify-between gap-3 print:hidden">
            <div class="flex flex-wrap items-end gap-2">
                <select wire:model.live="preset" class="rounded-md border-gray-300 text-sm shadow-sm">
                    @foreach ($presets as $val => $label)
                        <option value="{{ $val }}">{{ $label }}</option>
                    @endforeach
                </select>
                @if ($preset === 'custom')
                    <input type="date" wire:model.live="from" class="rounded-md border-gray-300 text-sm shadow-sm">
                    <input type="date" wire:model.live="to" class="rounded-md border-gray-300 text-sm shadow-sm">
                @endif
            </div>

            <div class="flex flex-wrap gap-2">
                @if ($canExport)
                    <a href="{{ route('reports.export', ['report' => $key] + $exportQuery + ['format' => 'csv']) }}"
                       class="px-3 py-1.5 text-sm rounded-md border border-gray-300 text-gray-700 hover:bg-gray-50">Unduh CSV</a>
                    <a href="{{ route('reports.export', ['report' => $key] + $exportQuery + ['format' => 'excel']) }}"
                       class="px-3 py-1.5 text-sm rounded-md border border-gray-300 text-gray-700 hover:bg-gray-50">Unduh untuk Excel</a>
                @endif
                <button type="button" onclick="window.print()"
                        class="px-3 py-1.5 text-sm rounded-md border border-gray-300 text-gray-700 hover:bg-gray-50">Cetak / simpan PDF</button>
            </div>
        </div>

        @if ($error)
            <div class="rounded-md border border-amber-300 bg-amber-50 p-3 text-sm text-amber-900 print:hidden">{{ $error }}</div>
        @endif

        <div class="bg-white shadow sm:rounded-lg p-6 space-y-4">
            <div>
                <h3 class="text-lg font-semibold text-gray-800">{{ $data['title'] }}</h3>
                <p class="text-sm text-gray-500">Periode {{ $period->label() }} &middot; {{ count($data['rows']) }} {{ $key === 'trend' ? 'bulan' : 'trip' }}</p>
            </div>

            {{-- Batang sederhana untuk tren --}}
            @if ($key === 'trend' && $data['rows'])
                <div class="space-y-2">
                    @foreach ($data['rows'] as $row)
                        <div class="flex items-center gap-3 text-sm">
                            <span class="w-32 shrink-0 text-gray-600">{{ $row['period'] }}</span>
                            <div class="h-3 flex-1 rounded bg-gray-100">
                                <div class="h-3 rounded bg-indigo-500" style="width: {{ $row['_bar'] !== null ? min(100, max(1, round((float) $row['_bar']))) : 0 }}%"></div>
                            </div>
                            <span class="w-40 shrink-0 text-right font-medium">{{ $rp($row['revenue']) }}</span>
                        </div>
                    @endforeach
                </div>
            @endif

            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200 text-sm">
                    <thead class="bg-gray-50">
                        <tr>
                            @foreach ($data['columns'] as $col)
                                <th class="px-3 py-2 font-medium text-gray-600 {{ in_array($col['type'], ['text'], true) ? 'text-left' : 'text-right' }}">{{ $col['label'] }}</th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @forelse ($data['rows'] as $row)
                            <tr>
                                @foreach ($data['columns'] as $col)
                                    <td class="px-3 py-2 {{ $col['type'] === 'text' ? '' : 'text-right whitespace-nowrap' }}">
                                        @if ($col['key'] === 'trip_code' && ($row['_trip_id'] ?? null))
                                            <a href="{{ route('reports.trip', $row['_trip_id']) }}" wire:navigate class="text-indigo-600 hover:underline print:no-underline">{{ $row['trip_code'] }}</a>
                                        @else
                                            {{ $cell($col['type'], $row[$col['key']] ?? null) }}
                                        @endif
                                    </td>
                                @endforeach
                            </tr>
                        @empty
                            <tr><td colspan="{{ count($data['columns']) }}" class="px-3 py-8 text-center text-gray-500">Tidak ada trip yang sudah direkonsiliasi pada periode ini.</td></tr>
                        @endforelse
                    </tbody>
                    @if ($data['totals'] !== null && $data['rows'])
                        <tfoot class="bg-gray-50 font-semibold">
                            <tr>
                                @foreach ($data['columns'] as $i => $col)
                                    <td class="px-3 py-2 {{ $col['type'] === 'text' ? '' : 'text-right whitespace-nowrap' }}">
                                        {{ $i === 0 ? 'Total' : $cell($col['type'], $data['totals'][$col['key']] ?? null) }}
                                    </td>
                                @endforeach
                            </tr>
                        </tfoot>
                    @endif
                </table>
            </div>

            @foreach ($data['notes'] as $note)
                <p class="text-xs text-gray-500">{{ $note }}</p>
            @endforeach
        </div>
    </div>
</div>
