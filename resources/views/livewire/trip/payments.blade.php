@php
    $rp = fn ($v) => \App\Support\Money::rp($v);
    $types = \App\Support\Options::PAYMENT_TYPES;
    $methods = \App\Support\Options::PAYMENT_METHODS;
    $stateLabels = [
        'unpaid' => ['Belum dibayar', 'bg-gray-100 text-gray-700'],
        'partial' => ['Dibayar sebagian', 'bg-amber-100 text-amber-800'],
        'paid' => ['Lunas', 'bg-green-100 text-green-800'],
        'overpaid' => ['Kelebihan bayar', 'bg-red-100 text-red-800'],
    ];
    $state = $stateLabels[$summary['state'] ?? 'unpaid'] ?? $stateLabels['unpaid'];
@endphp

<div class="py-8">
    <div class="max-w-5xl mx-auto sm:px-6 lg:px-8 space-y-6">

        <div>
            <a href="{{ route('trips.show', $trip->id) }}" wire:navigate class="text-sm text-indigo-600 hover:underline">&larr; Kembali ke trip</a>
            <div class="mt-1 flex flex-wrap items-center justify-between gap-3">
                <div>
                    <h2 class="text-xl font-semibold text-gray-800">Pembayaran</h2>
                    <p class="text-sm text-gray-500">{{ $trip->trip_code }} &middot; {{ $trip->trip_name }} &middot; {{ $trip->customer_name }}</p>
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

        @if ($summary['final'] === null)
            <div class="rounded-md border border-amber-300 bg-amber-50 p-4 text-sm text-amber-900">
                Harga final belum disepakati, jadi belum ada tagihan. Selesaikan negosiasi di halaman Harga &amp; negosiasi dulu.
            </div>
        @else
            <dl class="grid grid-cols-2 sm:grid-cols-4 gap-4 text-sm">
                <div class="rounded-lg bg-white shadow p-4">
                    <dt class="text-gray-500">Harga final (Revenue)</dt>
                    <dd class="text-lg font-semibold">{{ $rp($summary['final']) }}</dd>
                </div>
                <div class="rounded-lg bg-white shadow p-4">
                    <dt class="text-gray-500">Sudah dibayar</dt>
                    <dd class="text-lg font-semibold">{{ $rp($summary['paid']) }}</dd>
                </div>
                <div class="rounded-lg bg-white shadow p-4">
                    <dt class="text-gray-500">Sisa tagihan</dt>
                    <dd class="text-lg font-semibold">{{ $rp($summary['outstanding']) }}</dd>
                </div>
                <div class="rounded-lg bg-white shadow p-4">
                    <dt class="text-gray-500">Status pembayaran</dt>
                    <dd class="mt-1"><span class="px-2 py-0.5 rounded-full text-xs {{ $state[1] }}">{{ $state[0] }}</span></dd>
                </div>
            </dl>

            {{-- Riwayat --}}
            <div class="bg-white shadow sm:rounded-lg p-6 space-y-4">
                <h3 class="text-lg font-semibold text-gray-800">Riwayat pembayaran</h3>

                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 text-sm">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-3 py-2 text-left font-medium text-gray-600">Tanggal</th>
                                <th class="px-3 py-2 text-left font-medium text-gray-600">Jenis</th>
                                <th class="px-3 py-2 text-left font-medium text-gray-600">Metode</th>
                                <th class="px-3 py-2 text-left font-medium text-gray-600">Referensi</th>
                                <th class="px-3 py-2 text-right font-medium text-gray-600">Jumlah</th>
                                <th class="px-3 py-2"></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            @forelse ($summary['payments'] as $p)
                                @php
                                    $isVoid = $p->voided_at !== null;
                                @endphp
                                <tr wire:key="pay-{{ $p->id }}" class="{{ $isVoid ? 'bg-gray-50 text-gray-400' : '' }}">
                                    <td class="px-3 py-2">{{ $p->paid_at->format('d/m/Y') }}</td>
                                    <td class="px-3 py-2">
                                        {{ $types[$p->payment_type] ?? $p->payment_type }}@if ($p->installment_no) ke-{{ $p->installment_no }}@endif
                                        @if ($isVoid)
                                            <span class="ml-1 rounded bg-red-100 px-1.5 py-0.5 text-xs text-red-700">dibatalkan</span>
                                        @endif
                                        @if ($p->notes)
                                            <div class="text-xs text-gray-500">{{ $p->notes }}</div>
                                        @endif
                                        @if ($isVoid)
                                            <div class="text-xs text-red-600">Alasan: {{ $p->void_reason }}</div>
                                        @endif
                                    </td>
                                    <td class="px-3 py-2">{{ $methods[$p->method] ?? $p->method }}</td>
                                    <td class="px-3 py-2">{{ $p->reference ?? '-' }}</td>
                                    <td class="px-3 py-2 text-right font-medium {{ $isVoid ? 'line-through' : '' }}">{{ $rp($p->amount) }}</td>
                                    <td class="px-3 py-2 text-right">
                                        @if ($canManage && ! $isVoid)
                                            <button wire:click="startVoid({{ $p->id }})" class="text-red-600 hover:underline">Batalkan</button>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="6" class="px-3 py-6 text-center text-gray-500">Belum ada pembayaran.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                @if ($voidingId)
                    <form wire:submit="confirmVoid" class="rounded-md border border-red-200 bg-red-50 p-4 space-y-3">
                        <p class="text-sm text-red-800">Pembayaran yang dibatalkan tidak dihapus, hanya ditandai dan tidak dihitung lagi. Alasan wajib diisi.</p>
                        <div>
                            <input type="text" wire:model="voidReason" placeholder="Alasan pembatalan"
                                   class="w-full rounded-md border-red-200 text-sm shadow-sm">
                            @error('voidReason')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                        </div>
                        <div class="flex gap-3">
                            <button type="submit" class="px-3 py-1.5 text-sm rounded-md bg-red-600 text-white hover:bg-red-500">Batalkan pembayaran</button>
                            <button type="button" wire:click="cancelVoid" class="px-3 py-1.5 text-sm rounded-md border border-gray-300 text-gray-700 hover:bg-white">Tutup</button>
                        </div>
                    </form>
                @endif
            </div>

            {{-- Form catat pembayaran --}}
            @if ($canRecord)
                <form wire:submit="record" class="bg-white shadow sm:rounded-lg p-6 space-y-4">
                    <h3 class="text-lg font-semibold text-gray-800">Catat pembayaran</h3>
                    <p class="text-sm text-gray-500">Sisa tagihan saat ini <span class="font-medium text-gray-800">{{ $rp($summary['outstanding']) }}</span>. Jumlah tidak boleh melebihi sisa tagihan.</p>

                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Jenis</label>
                            <select wire:model.live="form.payment_type" class="mt-1 w-full rounded-md border-gray-300 text-sm shadow-sm">
                                @foreach ($types as $val => $label)
                                    <option value="{{ $val }}">{{ $label }}</option>
                                @endforeach
                            </select>
                            @error('form.payment_type')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Jumlah</label>
                            <x-money-input model="form.amount" class="mt-1" />
                            <button type="button" wire:click="fillOutstanding" class="mt-1 text-xs text-indigo-600 hover:underline">Isi sesuai sisa tagihan</button>
                            @error('form.amount')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Tanggal bayar</label>
                            <input type="date" wire:model="form.paid_at" max="{{ now()->toDateString() }}"
                                   class="mt-1 w-full rounded-md border-gray-300 text-sm shadow-sm">
                            @error('form.paid_at')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Metode</label>
                            <select wire:model="form.method" class="mt-1 w-full rounded-md border-gray-300 text-sm shadow-sm">
                                @foreach ($methods as $val => $label)
                                    <option value="{{ $val }}">{{ $label }}</option>
                                @endforeach
                            </select>
                            @error('form.method')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Referensi (opsional)</label>
                            <input type="text" wire:model="form.reference" placeholder="Nomor transfer, nama pengirim"
                                   class="mt-1 w-full rounded-md border-gray-300 text-sm shadow-sm">
                            @error('form.reference')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Catatan (opsional)</label>
                            <input type="text" wire:model="form.notes"
                                   class="mt-1 w-full rounded-md border-gray-300 text-sm shadow-sm">
                            @error('form.notes')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                        </div>
                    </div>

                    <div class="flex justify-end">
                        <button type="submit" class="px-4 py-2 text-sm font-medium rounded-md bg-indigo-600 text-white hover:bg-indigo-500">Simpan pembayaran</button>
                    </div>
                </form>
            @elseif ($summary['state'] === 'paid')
                <p class="text-sm text-green-700">Trip ini sudah lunas.</p>
            @endif
        @endif
    </div>
</div>