@php
    $fmt = fn ($v) => rtrim(rtrim(number_format((float) $v, 4, '.', ''), '0'), '.');
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
                {{-- baris Pax --}}
                <div>
                    <dt class="text-gray-500">Pax</dt>
                    <dd class="font-medium">
                        {{ $trip->pax }}@if ($trip->effectivePax() !== $trip->pax)<span class="text-xs text-gray-500"> (biaya dihitung dengan {{ $trip->effectivePax() }} confirmed pax)</span>@endif
                    </dd>
                </div>
                <div><dt class="text-gray-500">Staff</dt><dd class="font-medium">{{ $trip->staff?->name ?? '-' }}</dd></div>
                <div><dt class="text-gray-500">Operational Partner</dt><dd class="font-medium">{{ $trip->partner?->name ?? '-' }}</dd></div>
                <div><dt class="text-gray-500">Destinasi</dt><dd class="font-medium">{{ $destinations ? implode(', ', $destinations) : '-' }}</dd></div>
                @if ($trip->trip_type === 'open_trip')
                    <div><dt class="text-gray-500">Min / Maks / Confirmed</dt>
                        <dd class="font-medium">{{ $trip->min_pax ?? '-' }} / {{ $trip->max_capacity ?? '-' }} / {{ $trip->confirmed_pax ?? '-' }}</dd></div>
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
                @if ($canCancel)
                    <button wire:click="$set('showCancel', true)"
                            class="px-3 py-1.5 text-sm rounded-md border border-red-300 text-red-700 hover:bg-red-50">Batalkan trip</button>
                @endif
                {{-- tombol Harga & negosiasi --}}
                @can('view-costs')
                    <a href="{{ route('trips.pricing', $trip->id) }}" wire:navigate
                       class="px-3 py-1.5 text-sm rounded-md bg-emerald-600 text-white hover:bg-emerald-500">Harga &amp; negosiasi</a>
                @endcan
            </div>
        </div>

        {{-- Cost builder --}}
        <div class="bg-white shadow sm:rounded-lg p-6 space-y-4">
            <h3 class="text-lg font-semibold text-gray-800">Komponen biaya</h3>

            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200 text-sm">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-3 py-2 text-left font-medium text-gray-600">Komponen</th>
                            <th class="px-3 py-2 text-left font-medium text-gray-600">Tipe biaya</th>
                            <th class="px-3 py-2 text-left font-medium text-gray-600">Sifat</th>
                            <th class="px-3 py-2 text-right font-medium text-gray-600">Jumlah</th>
                            <th class="px-3 py-2 text-right font-medium text-gray-600">Durasi</th>
                            @if ($showMoney)
                                <th class="px-3 py-2 text-right font-medium text-gray-600">Tarif</th>
                                <th class="px-3 py-2 text-right font-medium text-gray-600">Subtotal</th>
                            @endif
                            <th class="px-3 py-2"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @forelse ($items->groupBy('category') as $category => $group)
                            <tr class="bg-gray-50">
                                <td colspan="{{ $showMoney ? 6 : 5 }}" class="px-3 py-2 font-semibold text-gray-700">{{ $category }}</td>
                                <td class="px-3 py-2 text-right font-semibold text-gray-700" @if(! $showMoney) hidden @endif>
                                    @if ($showMoney) {{ \App\Support\Money::rp($summary['by_category'][$category] ?? 0) }} @endif
                                </td>
                                <td></td>
                            </tr>
                            @foreach ($group as $item)
                                @php($line = $summary['lines'][$item->id] ?? null)
                                <tr wire:key="item-{{ $item->id }}">
                                    <td class="px-3 py-2">
                                        {{ $item->item_name }}
                                        @unless ($item->is_required)<span class="text-xs text-gray-400">(opsional)</span>@endunless
                                        @if ($item->notes)<div class="text-xs text-gray-500">{{ $item->notes }}</div>@endif
                                    </td>
                                    <td class="px-3 py-2">{{ \App\Support\Options::COST_TYPES[$item->cost_type] ?? $item->cost_type }}</td>
                                    <td class="px-3 py-2">{{ $item->cost_behavior === 'fixed' ? 'Fixed' : 'Variable' }}</td>
                                    <td class="px-3 py-2 text-right">
                                        {{-- [PERUBAHAN A] Logika tampilan Jumlah Orang --}}
                                        @if ($item->cost_type === 'per_person')
                                            {{ $item->people_count ?? $trip->effectivePax() }} orang
                                            @if ((float) $item->quantity !== 1.0)
                                                <span class="text-xs text-amber-600" title="Pengali tambahan">&times; {{ $fmt($item->quantity) }}</span>
                                            @endif
                                        @elseif ($item->unit_capacity)
                                            {{ $fmt($item->override_quantity ?? $item->calculated_quantity ?? $item->quantity) }}
                                            @if ($item->override_quantity !== null)
                                                <span class="text-xs text-amber-600" title="{{ $item->override_reason }}">
                                                    (override, dihitung {{ $fmt($item->calculated_quantity) }})
                                                </span>
                                            @endif
                                        @elseif ($item->cost_type === 'percentage')
                                            {{ $fmt($item->percentage) }}%
                                        @else
                                            {{ $fmt($item->quantity) }}
                                        @endif
                                    </td>
                                    <td class="px-3 py-2 text-right">
                                        @if (in_array($item->cost_type, ['per_room', 'per_night']))
                                            {{ $fmt($item->duration_qty ?? 1) }} malam
                                            @if ((float) ($item->duration_qty ?? 1) <= 0)
                                                <div class="text-xs text-amber-600">Biaya Rp0 karena 0 malam. Cek jumlah malam di data trip.</div>
                                            @endif
                                        @elseif ($item->cost_type === 'per_day')
                                            {{ $fmt($item->duration_qty ?? 1) }} hari
                                        @else
                                            -
                                        @endif
                                    </td>
                                    @if ($showMoney)
                                        <td class="px-3 py-2 text-right">{{ $item->cost_type === 'percentage' ? '-' : \App\Support\Money::rp($item->standard_cost) }}</td>
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
                            <tr><td colspan="8" class="px-3 py-8 text-center text-gray-500">Belum ada komponen biaya.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if ($editable)
                {{-- [PERUBAHAN B] Form Tambah Komponen --}}
                <form wire:submit="addItem" class="grid grid-cols-1 sm:grid-cols-8 gap-3 border-t pt-4">
                    <div>
                        <label class="block text-xs font-medium text-gray-600">Jenis</label>
                        <select wire:model.live="itemForm.item_type" class="mt-1 w-full rounded-md border-gray-300 text-sm shadow-sm">
                            <option value="">-- pilih --</option>
                            @foreach (\App\Support\Options::ITEM_TYPES as $val => $label)
                                <option value="{{ $val }}">{{ $label }}</option>
                            @endforeach
                        </select>
                        @error('itemForm.item_type')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                    </div>
                    <div class="sm:col-span-3">
                        <label class="block text-xs font-medium text-gray-600">Komponen</label>
                        <select wire:model="itemForm.item_id" class="mt-1 w-full rounded-md border-gray-300 text-sm shadow-sm">
                            <option value="">-- pilih --</option>
                            @foreach ($choices as $id => $label)
                                <option value="{{ $id }}">{{ $label }}</option>
                            @endforeach
                        </select>
                        @error('itemForm.item_id')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        {{-- [PERUBAHAN D] Ubah Label Jumlah menjadi Pengali --}}
                        <label class="block text-xs font-medium text-gray-600">Pengali (biasanya 1)</label>
                        <input type="number" step="0.0001" min="0" wire:model="itemForm.quantity"
                               class="mt-1 w-full rounded-md border-gray-300 text-sm shadow-sm">
                        @error('itemForm.quantity')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                    </div>
                    
                    {{-- [PERUBAHAN B] Tambah input Jumlah Orang --}}
                    <div class="sm:col-span-2">
                        <label class="block text-xs font-medium text-gray-600">Jumlah orang (opsional)</label>
                        <input type="number" min="1" max="{{ $trip->effectivePax() }}" wire:model="itemForm.people_count"
                               placeholder="Semua ({{ $trip->effectivePax() }})"
                               class="mt-1 w-full rounded-md border-gray-300 text-sm shadow-sm">
                        @error('itemForm.people_count')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                    </div>

                    <div class="flex items-end">
                        <button type="submit" class="w-full px-3 py-2 text-sm rounded-md bg-gray-800 text-white hover:bg-gray-700">+ Tambah</button>
                    </div>
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
                    Harga jual dan profit menyusul di Sprint 5. Angka ini dihitung server dari tarif yang tersimpan pada trip.
                </p>
            </div>
        @endif
    </div>

    {{-- Panel ubah komponen --}}
    @if ($editingId)
        @php($editItem = $items->firstWhere('id', $editingId))
        @if ($editItem)
            <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4">
                <div class="bg-white rounded-lg shadow-xl w-full max-w-lg max-h-[90vh] overflow-y-auto p-6">
                    <h3 class="text-lg font-semibold text-gray-800">Ubah komponen</h3>
                    <p class="text-sm text-gray-500">{{ $editItem->item_name }}</p>

                    <form wire:submit="saveEdit" class="mt-4 space-y-4">
                        <div>
                            {{-- [PERUBAHAN D] Ubah Label Jumlah menjadi Pengali --}}
                            <label class="block text-sm font-medium text-gray-700">Pengali (biasanya 1)</label>
                            <input type="number" step="0.0001" min="0" wire:model="edit.quantity"
                                   class="mt-1 w-full rounded-md border-gray-300 text-sm shadow-sm">
                            @error('edit.quantity')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                        </div>

                        @if (in_array($editItem->cost_type, ['per_room', 'per_night', 'per_day']))
                            <div>
                                <label class="block text-sm font-medium text-gray-700">
                                    Durasi ({{ $editItem->cost_type === 'per_day' ? 'hari' : 'malam' }})
                                </label>
                                <input type="number" step="0.5" min="0" wire:model="edit.duration_qty"
                                       class="mt-1 w-full rounded-md border-gray-300 text-sm shadow-sm">
                                @error('edit.duration_qty')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                            </div>
                        @endif

                        {{-- [PERUBAHAN C] Tambah input Jumlah Orang di Panel Edit --}}
                        @if ($editItem->cost_type === 'per_person')
                            <div>
                                <label class="block text-sm font-medium text-gray-700">Jumlah orang yang dikenai biaya</label>
                                <input type="number" min="1" max="{{ $trip->effectivePax() }}" wire:model="edit.people_count"
                                       placeholder="Semua peserta ({{ $trip->effectivePax() }})"
                                       class="mt-1 w-full rounded-md border-gray-300 text-sm shadow-sm">
                                <p class="mt-1 text-xs text-gray-500">Kosongkan bila biaya berlaku untuk semua peserta.</p>
                                @error('edit.people_count')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                            </div>
                        @endif

                        @if ($editItem->unit_capacity)
                            <div class="rounded-md bg-amber-50 p-3 space-y-3">
                                <p class="text-xs text-amber-800">
                                    Sistem menghitung {{ $fmt($editItem->calculated_quantity) }} unit
                                    (kapasitas {{ $editItem->unit_capacity }} orang per unit). Isi di bawah hanya jika ingin menggantinya.
                                </p>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700">Override jumlah unit</label>
                                    <input type="number" step="1" min="1" wire:model="edit.override_quantity"
                                           class="mt-1 w-full rounded-md border-gray-300 text-sm shadow-sm">
                                    @error('edit.override_quantity')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700">Alasan override</label>
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