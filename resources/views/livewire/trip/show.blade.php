@php
    $fmt = fn ($v) => rtrim(rtrim(number_format((float) $v, 4, '.', ''), '0'), '.');
    $unitTypes = \App\Services\TripItemFactory::UNIT_TYPES;
    $durationTypes = \App\Services\TripItemFactory::DURATION_TYPES;
    $qtyLabels = [
        'per_room' => 'Jumlah kamar',
        'per_vehicle' => 'Jumlah kendaraan',
        'per_day' => 'Jumlah (orang/unit)',
        'per_night' => 'Jumlah (unit)',
        'per_trip' => 'Jumlah (unit)',
    ];
    $paxNow = $trip->effectivePax();
@endphp

<div class="py-8">
    <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">

        {{-- Judul --}}
        <div>
            <a href="{{ route('trips.index') }}" wire:navigate class="text-sm text-indigo-600 hover:underline">&larr; Semua trip</a>
            <div class="mt-1 flex flex-wrap items-center justify-between gap-3">
                <div>
                    <h2 class="text-xl font-semibold text-gray-800">{{ $trip->trip_name }}</h2>
                    <p class="text-sm text-gray-500">
                        {{ $trip->trip_code }} &middot; {{ \App\Support\Options::TRIP_TYPES[$trip->trip_type] ?? $trip->trip_type }}
                    </p>
                </div>
                <span class="px-3 py-1 rounded-full text-sm {{ $trip->statusBadgeClass() }}">
                    {{ \App\Models\Trip::STATUS[$trip->status] ?? $trip->status }}
                </span>
            </div>
        </div>

        @if (session('status'))
            <div class="rounded-md bg-green-50 p-3 text-sm text-green-800">{{ session('status') }}</div>
        @endif
        @if (session('warning'))
            <div class="rounded-md border border-amber-300 bg-amber-50 p-3 text-sm text-amber-900">{{ session('warning') }}</div>
        @endif
        @if (session('error'))
            <div class="rounded-md bg-red-50 p-3 text-sm text-red-800">{{ session('error') }}</div>
        @endif

        {{-- Ringkasan trip --}}
        <div class="bg-white shadow sm:rounded-lg p-6">
            <dl class="grid grid-cols-2 sm:grid-cols-4 gap-4 text-sm">
                <div><dt class="text-gray-500">Customer</dt><dd class="font-medium">{{ $trip->customer_name }}</dd></div>
                <div><dt class="text-gray-500">Kontak</dt><dd class="font-medium">{{ $trip->customer_contact ?? '-' }}</dd></div>
                <div>
                    <dt class="text-gray-500">Tanggal</dt>
                    <dd class="font-medium">{{ $trip->start_date->format('d/m/Y') }} - {{ $trip->end_date->format('d/m/Y') }}</dd>
                </div>
                <div><dt class="text-gray-500">Durasi</dt><dd class="font-medium">{{ $trip->duration_days }} hari / {{ $trip->nights }} malam</dd></div>
                <div>
                    <dt class="text-gray-500">Pax</dt>
                    <dd class="font-medium">
                        {{ $trip->pax }}
                        @if ($paxNow !== $trip->pax)
                            <span class="text-xs text-gray-500">(biaya dihitung dengan {{ $paxNow }} confirmed pax)</span>
                        @endif
                    </dd>
                </div>
                <div><dt class="text-gray-500">Staff</dt><dd class="font-medium">{{ $trip->staff?->name ?? '-' }}</dd></div>
                <div><dt class="text-gray-500">Operational Partner</dt><dd class="font-medium">{{ $trip->partner?->name ?? '-' }}</dd></div>
                <div><dt class="text-gray-500">Destinasi</dt><dd class="font-medium">{{ $destinations ? implode(', ', $destinations) : '-' }}</dd></div>
                @if ($trip->trip_type === 'open_trip')
                    <div>
                        <dt class="text-gray-500">Min / Maks / Confirmed</dt>
                        <dd class="font-medium">{{ $trip->min_pax ?? '-' }} / {{ $trip->max_capacity ?? '-' }} / {{ $trip->confirmed_pax ?? '-' }}</dd>
                    </div>
                @endif
            </dl>

            @if ($trip->notes)
                <p class="mt-4 text-sm text-gray-600"><span class="font-medium">Catatan:</span> {{ $trip->notes }}</p>
            @endif
            @if ($trip->status === 'cancelled')
                <p class="mt-4 text-sm text-red-700"><span class="font-medium">Alasan batal:</span> {{ $trip->cancel_reason }}</p>
            @endif

            <div class="mt-4 flex flex-wrap gap-3">
                @if ($editable)
                    <a href="{{ route('trips.edit', $trip->id) }}" wire:navigate
                       class="px-3 py-1.5 text-sm rounded-md border border-gray-300 text-gray-700 hover:bg-gray-50">Ubah data trip</a>
                    @if ($trip->status === 'draft')
                        <button wire:click="changeStatus('estimated')"
                                class="px-3 py-1.5 text-sm rounded-md bg-blue-600 text-white hover:bg-blue-500">Tandai Estimated</button>
                    @endif
                    @if ($trip->status === 'estimated')
                        <button wire:click="changeStatus('draft')"
                                class="px-3 py-1.5 text-sm rounded-md border border-gray-300 text-gray-700 hover:bg-gray-50">Kembali ke Draft</button>
                    @endif
                @endif
                @if ($canAdvance && in_array($trip->status, ['deposit_paid', 'paid'], true))
                    <button wire:click="changeStatus('ongoing')"
                            wire:confirm="Tandai trip sedang berjalan (Ongoing)?"
                            class="px-3 py-1.5 text-sm rounded-md bg-blue-600 text-white hover:bg-blue-500">Mulai trip</button>
                @endif
                @if ($canAdvance && $trip->status === 'ongoing')
                    <button wire:click="changeStatus('completed')"
                            wire:confirm="Tandai trip selesai (Completed)? Setelah ini biaya aktual dilengkapi lalu direkonsiliasi."
                            class="px-3 py-1.5 text-sm rounded-md bg-blue-600 text-white hover:bg-blue-500">Selesaikan trip</button>
                @endif
                @if ($canCancel)
                    <button wire:click="$set('showCancel', true)"
                            class="px-3 py-1.5 text-sm rounded-md border border-red-300 text-red-700 hover:bg-red-50">Batalkan trip</button>
                @endif
                @can('view-finance')
                    @if (in_array($trip->status, ['confirmed', 'deposit_paid', 'paid', 'ongoing', 'completed', 'reconciled'], true))
                        <a href="{{ route('trips.payments', $trip->id) }}" wire:navigate
                           class="px-3 py-1.5 text-sm rounded-md border border-gray-300 text-gray-700 hover:bg-gray-50">Pembayaran</a>
                    @endif
                @endcan
                @can('view-costs')
                    @if (in_array($trip->status, ['ongoing', 'completed', 'reconciled'], true))
                        <a href="{{ route('trips.reconcile', $trip->id) }}" wire:navigate
                           class="px-3 py-1.5 text-sm rounded-md border border-indigo-300 text-indigo-700 hover:bg-indigo-50">Biaya aktual &amp; rekonsiliasi</a>
                    @endif
                @endcan
                @can('view-costs')
                    <a href="{{ route('trips.pricing', $trip->id) }}" wire:navigate
                       class="px-3 py-1.5 text-sm rounded-md bg-emerald-600 text-white hover:bg-emerald-500">Harga &amp; negosiasi</a>
                @endcan
            </div>
        </div>

        {{-- Komponen biaya --}}
        <div class="bg-white shadow sm:rounded-lg p-6 space-y-4">
            <h3 class="text-lg font-semibold text-gray-800">Komponen biaya</h3>

            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200 text-sm">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-3 py-2 text-left font-medium text-gray-600">Komponen</th>
                            <th class="px-3 py-2 text-left font-medium text-gray-600">Tipe biaya</th>
                            <th class="px-3 py-2 text-left font-medium text-gray-600">Sifat</th>
                            <th class="px-3 py-2 text-left font-medium text-gray-600">Perhitungan</th>
                            @if ($showMoney)
                                <th class="px-3 py-2 text-right font-medium text-gray-600">Subtotal</th>
                            @endif
                            <th class="px-3 py-2"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @forelse ($items->groupBy('category') as $category => $group)
                            <tr class="bg-gray-50">
                                <td colspan="4" class="px-3 py-2 font-semibold text-gray-700">{{ $category }}</td>
                                @if ($showMoney)
                                    <td class="px-3 py-2 text-right font-semibold text-gray-700">
                                        {{ \App\Support\Money::rp($summary['by_category'][$category] ?? 0) }}
                                    </td>
                                @endif
                                <td></td>
                            </tr>

                            @foreach ($group as $item)
                                @php
                                    $line = $summary['lines'][$item->id] ?? null;
                                    $dupKey = $item->item_type.'#'.$item->item_id;
                                    $zeroDuration = in_array($item->cost_type, ['per_room', 'per_night'], true)
                                        && (float) ($item->duration_qty ?? 1) <= 0;
                                @endphp
                                <tr wire:key="item-{{ $item->id }}">
                                    <td class="px-3 py-2">
                                        {{ $item->item_name }}
                                        @if ($item->is_additional)
                                            <span class="rounded bg-sky-100 px-1.5 py-0.5 text-xs text-sky-800">tambahan</span>
                                        @elseif (! $item->is_required)
                                            <span class="text-xs text-gray-400">(opsional)</span>
                                        @endif
                                        @if (($dupes[$dupKey] ?? 0) > 1)
                                            <span class="ml-1 rounded bg-amber-100 px-1.5 py-0.5 text-xs text-amber-800"
                                                  title="Komponen yang sama muncul lebih dari satu kali di trip ini">{{ $dupes[$dupKey] }} baris</span>
                                        @endif
                                        @if ($item->notes)
                                            <div class="text-xs text-gray-500">{{ $item->notes }}</div>
                                        @endif
                                    </td>
                                    <td class="px-3 py-2">{{ \App\Support\Options::COST_TYPES[$item->cost_type] ?? $item->cost_type }}</td>
                                    <td class="px-3 py-2">{{ $item->cost_behavior === 'fixed' ? 'Fixed' : 'Variable' }}</td>
                                    <td class="px-3 py-2">
                                        {{ \App\Support\CostExplain::forItem($item, $line, $paxNow, $showMoney) }}
                                        @if ($item->override_quantity !== null)
                                            <span class="text-xs text-amber-600" title="{{ $item->override_reason }}">
                                                (manual, otomatis {{ $fmt($item->calculated_quantity) }})
                                            </span>
                                        @endif
                                        @if ($zeroDuration)
                                            <div class="text-xs text-amber-600">0 malam, biaya Rp0. Cek jumlah malam di data trip.</div>
                                        @endif
                                    </td>
                                    @if ($showMoney)
                                        <td class="px-3 py-2 text-right font-medium">{{ \App\Support\Money::rp($line['amount'] ?? 0) }}</td>
                                    @endif
                                    <td class="px-3 py-2 text-right whitespace-nowrap space-x-3">
                                        @if ($editable)
                                            <button wire:click="startEdit({{ $item->id }})" class="text-indigo-600 hover:underline">Ubah</button>
                                            <button wire:click="removeItem({{ $item->id }})" wire:confirm="Hapus komponen ini dari trip?"
                                                    class="text-red-600 hover:underline">Hapus</button>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        @empty
                            <tr><td colspan="6" class="px-3 py-8 text-center text-gray-500">Belum ada komponen biaya.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            {{-- Form tambah komponen --}}
            @if ($editable)
                <form wire:submit="addItem" class="space-y-4 border-t pt-4">
                    <h4 class="text-sm font-semibold text-gray-800">Tambah komponen biaya</h4>

                    <div class="grid grid-cols-1 sm:grid-cols-6 gap-3">
                        <div class="sm:col-span-2">
                            <label class="block text-xs font-medium text-gray-600">Jenis</label>
                            <select wire:model.live="itemForm.item_type" class="mt-1 w-full rounded-md border-gray-300 text-sm shadow-sm">
                                <option value="">-- pilih --</option>
                                @foreach (\App\Support\Options::ITEM_TYPES as $val => $label)
                                    <option value="{{ $val }}">{{ $label }}</option>
                                @endforeach
                            </select>
                            @error('itemForm.item_type')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                        </div>
                        <div class="sm:col-span-4">
                            <label class="block text-xs font-medium text-gray-600">Komponen</label>
                            <select wire:model.live="itemForm.item_id" class="mt-1 w-full rounded-md border-gray-300 text-sm shadow-sm">
                                <option value="">-- pilih --</option>
                                @foreach ($choices as $id => $label)
                                    <option value="{{ $id }}">{{ $label }}</option>
                                @endforeach
                            </select>
                            @error('itemForm.item_id')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                        </div>
                    </div>

                    @if ($formInfo)
                        @php
                            $ft = $formInfo['type'];
                        @endphp

                        <div class="grid grid-cols-1 sm:grid-cols-6 gap-3">
                            <div class="sm:col-span-2">
                                <label class="block text-xs font-medium text-gray-600">Tipe biaya</label>
                                <select wire:model.live="itemForm.cost_type" class="mt-1 w-full rounded-md border-gray-300 text-sm shadow-sm">
                                    @foreach ($addTypeOptions as $val => $label)
                                        <option value="{{ $val }}">{{ $label }}</option>
                                    @endforeach
                                </select>
                                @error('itemForm.cost_type')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                            </div>

                            @if ($ft === 'per_person')
                                <div class="sm:col-span-2">
                                    <label class="block text-xs font-medium text-gray-600">Jumlah orang</label>
                                    <input type="number" min="1" max="{{ $paxNow }}" wire:model.live.debounce.400ms="itemForm.people_count"
                                           placeholder="Semua peserta ({{ $paxNow }})"
                                           class="mt-1 w-full rounded-md border-gray-300 text-sm shadow-sm">
                                    <p class="mt-1 text-xs text-gray-500">Kosongkan bila berlaku untuk semua peserta.</p>
                                    @error('itemForm.people_count')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                                </div>
                            @elseif ($ft === 'percentage')
                                <div class="sm:col-span-4">
                                    <p class="mt-6 text-xs text-gray-500">Biaya persentase mengikuti pengaturan komponen di data master.</p>
                                </div>
                            @elseif ($formInfo['has_cap'])
                                <div class="sm:col-span-2">
                                    <label class="block text-xs font-medium text-gray-600">Jumlah unit</label>
                                    <p class="mt-2 text-sm text-gray-700">
                                        Otomatis: <span class="font-semibold">{{ $formInfo['auto_units'] }}</span>
                                        (kapasitas {{ $formInfo['capacity'] }} orang per unit)
                                    </p>
                                    <p class="text-xs text-gray-500">Bisa diganti manual lewat Ubah, dengan alasan.</p>
                                </div>
                            @else
                                <div class="sm:col-span-2">
                                    <label class="block text-xs font-medium text-gray-600">{{ $qtyLabels[$ft] ?? 'Jumlah' }}</label>
                                    <input type="number" step="1" min="1" wire:model.live.debounce.400ms="itemForm.quantity"
                                           class="mt-1 w-full rounded-md border-gray-300 text-sm shadow-sm">
                                    @error('itemForm.quantity')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                                </div>
                            @endif

                            @if (in_array($ft, $durationTypes, true))
                                <div class="sm:col-span-2">
                                    <label class="block text-xs font-medium text-gray-600">
                                        {{ $ft === 'per_day' ? 'Jumlah hari' : 'Jumlah malam' }}
                                    </label>
                                    <input type="number" step="0.5" min="0.5" wire:model.live.debounce.400ms="itemForm.duration_qty"
                                           class="mt-1 w-full rounded-md border-gray-300 text-sm shadow-sm">
                                    @error('itemForm.duration_qty')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                                </div>
                            @endif
                        </div>

                        @if ($showMoney && $formInfo['preview'])
                            <div class="rounded-md bg-indigo-50 p-3 text-sm text-indigo-900">
                                Perkiraan: {{ $formInfo['preview']['text'] }} =
                                <span class="font-semibold">{{ \App\Support\Money::rp($formInfo['preview']['amount']) }}</span>
                            </div>
                        @endif

                        <div class="flex justify-end">
                            <button type="submit" class="px-4 py-2 text-sm rounded-md bg-gray-800 text-white hover:bg-gray-700">+ Tambah</button>
                        </div>
                    @endif
                </form>
            @endif
        </div>

        {{-- Ringkasan biaya --}}
        @if ($showMoney && $summary)
            <div class="bg-white shadow sm:rounded-lg p-6">
                <h3 class="text-lg font-semibold text-gray-800">Ringkasan biaya (Standard Cost)</h3>
                <dl class="mt-4 grid grid-cols-2 sm:grid-cols-4 gap-4 text-sm">
                    <div class="sm:col-span-2 rounded-md bg-indigo-50 p-4">
                        <dt class="text-indigo-700">Total Standard Cost</dt>
                        <dd class="text-2xl font-semibold text-indigo-900">{{ \App\Support\Money::rp($summary['total']) }}</dd>
                    </div>
                    <div class="rounded-md bg-gray-50 p-4">
                        <dt class="text-gray-500">Biaya per orang</dt>
                        <dd class="font-semibold">{{ \App\Support\Money::rp($summary['cost_per_person']) }}</dd>
                    </div>
                    <div class="rounded-md bg-gray-50 p-4">
                        <dt class="text-gray-500">Fixed / Variable</dt>
                        <dd class="font-semibold">{{ \App\Support\Money::rp($summary['fixed_total']) }} / {{ \App\Support\Money::rp($summary['variable_total']) }}</dd>
                    </div>
                </dl>
                <p class="mt-3 text-xs text-gray-500">
                    Angka ini dihitung server dari tarif yang tersimpan pada trip. Harga jual dan negosiasi ada di halaman Harga &amp; negosiasi.
                </p>
            </div>
        @endif
    </div>

    {{-- Panel ubah komponen --}}
    @if ($editingId)
        @php
            $editItem = $items->firstWhere('id', $editingId);
        @endphp
        @if ($editItem)
            @php
                $et = $edit['cost_type'] !== '' ? $edit['cost_type'] : $editItem->cost_type;
                $eCap = $editItem->unit_capacity && in_array($et, $unitTypes, true);
                $eOptions = $editItem->cost_type === 'percentage'
                    ? ['percentage' => \App\Support\Options::COST_TYPES['percentage']]
                    : array_diff_key(\App\Support\Options::COST_TYPES, ['percentage' => true]);
            @endphp
            <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4">
                <div class="bg-white rounded-lg shadow-xl w-full max-w-lg max-h-[90vh] overflow-y-auto p-6">
                    <h3 class="text-lg font-semibold text-gray-800">Ubah komponen</h3>
                    <p class="text-sm text-gray-500">{{ $editItem->item_name }}</p>

                    <form wire:submit="saveEdit" class="mt-4 space-y-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Tipe biaya</label>
                            <select wire:model.live="edit.cost_type" class="mt-1 w-full rounded-md border-gray-300 text-sm shadow-sm">
                                @foreach ($eOptions as $val => $label)
                                    <option value="{{ $val }}">{{ $label }}</option>
                                @endforeach
                            </select>
                            @error('edit.cost_type')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                        </div>

                        @if ($et === 'per_person')
                            <div>
                                <label class="block text-sm font-medium text-gray-700">Jumlah orang</label>
                                <input type="number" min="1" max="{{ $paxNow }}" wire:model="edit.people_count"
                                       placeholder="Semua peserta ({{ $paxNow }})"
                                       class="mt-1 w-full rounded-md border-gray-300 text-sm shadow-sm">
                                <p class="mt-1 text-xs text-gray-500">Kosongkan bila berlaku untuk semua peserta.</p>
                                @error('edit.people_count')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                            </div>
                        @elseif ($et === 'percentage')
                            <p class="text-xs text-gray-500">Biaya persentase mengikuti pengaturan komponen di data master.</p>
                        @elseif (! $eCap)
                            <div>
                                <label class="block text-sm font-medium text-gray-700">{{ $qtyLabels[$et] ?? 'Jumlah' }}</label>
                                <input type="number" step="1" min="1" wire:model="edit.quantity"
                                       class="mt-1 w-full rounded-md border-gray-300 text-sm shadow-sm">
                                @error('edit.quantity')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                            </div>
                        @endif

                        @if (in_array($et, $durationTypes, true))
                            <div>
                                <label class="block text-sm font-medium text-gray-700">{{ $et === 'per_day' ? 'Jumlah hari' : 'Jumlah malam' }}</label>
                                <input type="number" step="0.5" min="0.5" wire:model="edit.duration_qty"
                                       class="mt-1 w-full rounded-md border-gray-300 text-sm shadow-sm">
                                @error('edit.duration_qty')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                            </div>
                        @endif

                        @if ($eCap)
                            <div class="rounded-md bg-amber-50 p-3 space-y-3">
                                <p class="text-xs text-amber-800">
                                    Sistem menghitung
                                    {{ app(\App\Services\TripCalculator::class)->calculateRoomRequirement($paxNow, (int) $editItem->unit_capacity) }}
                                    unit (kapasitas {{ $editItem->unit_capacity }} orang per unit). Isi di bawah hanya jika ingin menggantinya.
                                </p>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700">Jumlah unit manual</label>
                                    <input type="number" step="1" min="1" wire:model="edit.override_quantity"
                                           class="mt-1 w-full rounded-md border-gray-300 text-sm shadow-sm">
                                    @error('edit.override_quantity')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700">Alasan</label>
                                    <input type="text" wire:model="edit.override_reason"
                                           class="mt-1 w-full rounded-md border-gray-300 text-sm shadow-sm">
                                    @error('edit.override_reason')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                                </div>
                            </div>
                        @endif

                        <label class="flex items-center gap-2 text-sm text-gray-700">
                            <input type="checkbox" wire:model="edit.is_required" class="rounded border-gray-300 text-indigo-600 shadow-sm">
                            Wajib (harus punya actual cost saat rekonsiliasi)
                        </label>

                        <div>
                            <label class="block text-sm font-medium text-gray-700">Catatan</label>
                            <input type="text" wire:model="edit.notes" class="mt-1 w-full rounded-md border-gray-300 text-sm shadow-sm">
                            @error('edit.notes')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                        </div>

                        <div class="flex justify-end gap-3">
                            <button type="button" wire:click="closeEdit"
                                    class="px-4 py-2 text-sm rounded-md border border-gray-300 text-gray-700 hover:bg-gray-50">Batal</button>
                            <button type="submit" class="px-4 py-2 text-sm rounded-md bg-indigo-600 text-white hover:bg-indigo-500">Simpan</button>
                        </div>
                    </form>
                </div>
            </div>
        @endif
    @endif

    {{-- Panel pembatalan --}}
    @if ($showCancel)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4">
            <div class="bg-white rounded-lg shadow-xl w-full max-w-md p-6">
                <h3 class="text-lg font-semibold text-gray-800">Batalkan trip</h3>
                <p class="text-sm text-gray-500">Pembatalan tidak bisa diurungkan. Alasan wajib diisi dan tercatat di audit log.</p>
                <form wire:submit="cancelTrip" class="mt-4 space-y-4">
                    <div>
                        <textarea wire:model="cancelReason" rows="3" placeholder="Alasan pembatalan"
                                  class="w-full rounded-md border-gray-300 text-sm shadow-sm"></textarea>
                        @error('cancelReason')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                    </div>
                    <div class="flex justify-end gap-3">
                        <button type="button" wire:click="$set('showCancel', false)"
                                class="px-4 py-2 text-sm rounded-md border border-gray-300 text-gray-700 hover:bg-gray-50">Tutup</button>
                        <button type="submit" class="px-4 py-2 text-sm rounded-md bg-red-600 text-white hover:bg-red-500">Batalkan trip</button>
                    </div>
                </form>
            </div>
        </div>
    @endif
</div>