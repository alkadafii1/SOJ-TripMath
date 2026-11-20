@php
    $pct = fn ($v) => rtrim(rtrim(number_format((float) $v, 4, ',', '.'), '0'), ',').'%';
    $stateLabels = [
        'active' => ['Berlaku', 'bg-green-100 text-green-800'],
        'scheduled' => ['Akan berlaku', 'bg-blue-100 text-blue-800'],
        'ended' => ['Berakhir', 'bg-gray-100 text-gray-600'],
        'inactive' => ['Nonaktif', 'bg-red-100 text-red-700'],
    ];
@endphp

<div class="py-8">
    <div class="max-w-5xl mx-auto sm:px-6 lg:px-8 space-y-6">

        <div>
            <h2 class="text-xl font-semibold text-gray-800">Aturan Profit</h2>
            <p class="text-sm text-gray-500">Marketing allocation dan bagi hasil Owner dengan Operational Partner. Aturan yang berlaku saat rekonsiliasi dibekukan di snapshot trip.</p>
        </div>

        @if (session('status'))
            <div class="rounded-md bg-green-50 p-3 text-sm text-green-800">{{ session('status') }}</div>
        @endif
        @if (session('error'))
            <div class="rounded-md bg-red-50 p-3 text-sm text-red-800">{{ session('error') }}</div>
        @endif

        {{-- Aturan yang berlaku sekarang --}}
        @if ($current)
            <div class="rounded-lg bg-indigo-50 p-4 text-sm text-indigo-900">
                <p class="font-semibold">Aturan yang berlaku sekarang (sejak {{ $current['effective_from']->format('d/m/Y') }})</p>
                <p class="mt-1">
                    Marketing {{ $pct($current['marketing']['percent']) }} dari {{ $bases[$current['marketing']['base']] ?? $current['marketing']['base'] }}.
                    Sisanya (Distributable Profit) dibagi: Owner {{ $pct($current['shares']['owner']['percent']) }},
                    Operational Partner {{ $pct($current['shares']['partner']['percent']) }}.
                </p>
            </div>
        @else
            <div class="rounded-md border border-amber-300 bg-amber-50 p-4 text-sm text-amber-900">
                Belum ada aturan profit yang berlaku. Trip belum bisa direkonsiliasi sebelum aturan dibuat.
            </div>
        @endif

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            {{-- Form --}}
            <form wire:submit="save" class="bg-white shadow sm:rounded-lg p-6 space-y-4">
                <h3 class="text-lg font-semibold text-gray-800">Buat aturan baru</h3>

                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Marketing (%)</label>
                        <input type="number" step="0.01" min="0" max="100" wire:model.live.debounce.400ms="form.marketing_percent"
                               class="mt-1 w-full rounded-md border-gray-300 text-sm shadow-sm">
                        @error('form.marketing_percent')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Dihitung dari</label>
                        <select wire:model.live="form.marketing_base" class="mt-1 w-full rounded-md border-gray-300 text-sm shadow-sm">
                            @foreach ($bases as $val => $label)
                                <option value="{{ $val }}">{{ $label }}</option>
                            @endforeach
                        </select>
                        @error('form.marketing_base')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Owner (%)</label>
                        <input type="number" step="0.01" min="0" max="100" wire:model.live.debounce.400ms="form.owner_percent"
                               class="mt-1 w-full rounded-md border-gray-300 text-sm shadow-sm">
                        @error('form.owner_percent')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Operational Partner (%)</label>
                        <input type="number" step="0.01" min="0" max="100" wire:model.live.debounce.400ms="form.partner_percent"
                               class="mt-1 w-full rounded-md border-gray-300 text-sm shadow-sm">
                        @error('form.partner_percent')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                    </div>
                </div>

                @if ($total !== null)
                    <p class="text-sm {{ $totalOk ? 'text-green-700' : 'text-red-600' }}">
                        Total bagi hasil: {{ $pct($total) }} {{ $totalOk ? '(sudah 100%)' : '(harus tepat 100%)' }}
                    </p>
                @endif

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Berlaku mulai</label>
                        <input type="date" wire:model="form.effective_from" min="{{ today()->toDateString() }}"
                               class="mt-1 w-full rounded-md border-gray-300 text-sm shadow-sm">
                        @error('form.effective_from')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Alasan perubahan</label>
                        <input type="text" wire:model="form.reason" placeholder="Contoh: kesepakatan baru dengan partner"
                               class="mt-1 w-full rounded-md border-gray-300 text-sm shadow-sm">
                        @error('form.reason')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                    </div>
                </div>

                <p class="text-xs text-gray-500">
                    Aturan lama yang masih berlaku pada tanggal itu diakhiri sehari sebelumnya. Trip yang sudah direkonsiliasi tidak berubah.
                </p>

                <div class="flex justify-end">
                    <button type="submit" class="px-4 py-2 text-sm font-medium rounded-md bg-indigo-600 text-white hover:bg-indigo-500">Simpan aturan</button>
                </div>
            </form>

            {{-- Contoh perhitungan --}}
            <div class="space-y-2">
                @if ($example)
                    <x-profit-waterfall :w="$example" title="Contoh dengan angka spesifikasi" />
                    <p class="text-xs text-gray-500">Contoh memakai Revenue Rp10.000.000 dan Actual Cost Rp6.890.000, hanya untuk membantu memeriksa aturan.</p>
                @else
                    <div class="rounded-md border border-dashed border-gray-300 p-4 text-sm text-gray-500">
                        Isi persentase yang valid (total Owner dan Partner 100%) untuk melihat contoh perhitungannya.
                    </div>
                @endif
            </div>
        </div>

        {{-- Riwayat --}}
        <div class="bg-white shadow sm:rounded-lg p-6 space-y-4">
            <h3 class="text-lg font-semibold text-gray-800">Riwayat aturan</h3>

            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200 text-sm">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-3 py-2 text-left font-medium text-gray-600">Berlaku</th>
                            <th class="px-3 py-2 text-left font-medium text-gray-600">Marketing</th>
                            <th class="px-3 py-2 text-right font-medium text-gray-600">Owner</th>
                            <th class="px-3 py-2 text-right font-medium text-gray-600">Partner</th>
                            <th class="px-3 py-2 text-left font-medium text-gray-600">Status</th>
                            <th class="px-3 py-2"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @forelse ($sets as $set)
                            @php
                                $label = $stateLabels[$set['state']];
                            @endphp
                            <tr wire:key="set-{{ $set['rule_set'] }}">
                                <td class="px-3 py-2">
                                    {{ $set['effective_from']->format('d/m/Y') }}
                                    @if ($set['effective_to']) s/d {{ $set['effective_to']->format('d/m/Y') }} @endif
                                </td>
                                <td class="px-3 py-2">{{ $pct($set['marketing_percent']) }} dari {{ $bases[$set['marketing_base']] ?? $set['marketing_base'] }}</td>
                                <td class="px-3 py-2 text-right">{{ $pct($set['owner_percent']) }}</td>
                                <td class="px-3 py-2 text-right">{{ $pct($set['partner_percent']) }}</td>
                                <td class="px-3 py-2">
                                    <span class="px-2 py-0.5 rounded-full text-xs {{ $label[1] }}">{{ $label[0] }}</span>
                                    @if ($set['is_current'])<span class="ml-1 text-xs text-indigo-600">dipakai sekarang</span>@endif
                                </td>
                                <td class="px-3 py-2 text-right">
                                    @if (in_array($set['state'], ['active', 'scheduled'], true))
                                        <button type="button" wire:click="startDeactivate('{{ $set['rule_set'] }}')" class="text-red-600 hover:underline">Nonaktifkan</button>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="px-3 py-6 text-center text-gray-500">Belum ada aturan.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if ($deactivatingSet)
                <form wire:submit="confirmDeactivate" class="rounded-md border border-red-200 bg-red-50 p-4 space-y-3">
                    <p class="text-sm text-red-800">Tanpa aturan yang berlaku, trip tidak bisa direkonsiliasi. Alasan wajib diisi.</p>
                    <div>
                        <input type="text" wire:model="deactivateReason" placeholder="Alasan" class="w-full rounded-md border-red-200 text-sm shadow-sm">
                        @error('deactivateReason')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                    </div>
                    <div class="flex gap-3">
                        <button type="submit" class="px-3 py-1.5 text-sm rounded-md bg-red-600 text-white hover:bg-red-500">Nonaktifkan</button>
                        <button type="button" wire:click="cancelDeactivate" class="px-3 py-1.5 text-sm rounded-md border border-gray-300 text-gray-700 hover:bg-white">Tutup</button>
                    </div>
                </form>
            @endif
        </div>
    </div>
</div>
