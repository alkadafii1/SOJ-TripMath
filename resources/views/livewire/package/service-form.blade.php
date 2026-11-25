@if ($editable)
    <form wire:submit="addItem" class="space-y-4 border-t pt-5">
        <h4 class="font-semibold text-gray-800">{{ $editingItemId ? 'Ubah layanan' : 'Tambahkan layanan dari master' }}</h4>
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div>
                <label for="service-type" class="block text-sm font-medium text-gray-700">Jenis layanan</label>
                <select id="service-type" wire:model.live="itemForm.item_type" class="mt-1 w-full rounded-md border-gray-300 text-sm shadow-sm">
                    <option value="">Pilih jenis layanan</option>
                    @foreach (\App\Support\Options::ITEM_TYPES as $value => $label)
                        <option value="{{ $value }}">{{ $label }}</option>
                    @endforeach
                </select>
                <p class="mt-1 text-xs text-gray-500">Guide, parkir, tol, dan operasional dapat dipilih melalui Biaya Lain.</p>
                @error('itemForm.item_type')<p class="text-sm text-red-600">{{ $message }}</p>@enderror
            </div>
            <div>
                <label for="service-master" class="block text-sm font-medium text-gray-700">Layanan / penyedia</label>
                <select id="service-master" wire:model.live="itemForm.item_id" @disabled(! $itemForm['item_type'])
                        class="mt-1 w-full rounded-md border-gray-300 text-sm shadow-sm disabled:bg-gray-100">
                    <option value="">Pilih layanan master</option>
                    @foreach ($choices as $id => $label)
                        <option value="{{ $id }}">{{ $label }}</option>
                    @endforeach
                </select>
                @if ($itemForm['item_type'] && ! count($choices))
                    <p class="mt-1 text-xs text-amber-700">Belum ada master aktif untuk jenis ini.</p>
                @endif
                @error('itemForm.item_id')<p class="text-sm text-red-600">{{ $message }}</p>@enderror
            </div>
        </div>
        @if ($serviceInfo)
            <div class="rounded-md bg-indigo-50 p-3 text-sm text-indigo-900">
                <p class="font-medium">{{ \App\Support\Options::COST_TYPES[$serviceInfo['cost_type']] ?? $serviceInfo['cost_type'] }}</p>
                @if ($serviceInfo['automatic_quantity'])
                    @if ($serviceInfo['unit_capacity'])
                        <p>Jumlah {{ $serviceInfo['cost_type'] === 'per_room' ? 'kamar' : 'unit' }} otomatis dari pax dan kapasitas {{ $serviceInfo['unit_capacity'] }} orang/unit.</p>
                    @elseif ($serviceInfo['cost_type'] === 'per_person')
                        <p>Berlaku untuk seluruh peserta trip. Satu baris = satu layanan; untuk makan/tiket berulang, tambahkan layanan per hari atau waktu makan.</p>
                    @else
                        <p>Persentase mengikuti konfigurasi komponen master; dasar dihitung pada trip.</p>
                    @endif
                @endif
                @if ($showMoney)
                    <p class="mt-1 text-xs">Referensi tarif master: {{ $serviceInfo['cost_type'] === 'percentage' ? $serviceInfo['percentage'].'%' : \App\Support\Money::rp($serviceInfo['standard_cost']) }}. Bukan harga jual paket.</p>
                @endif
            </div>
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                <div>
                    <label for="service-day" class="block text-sm font-medium text-gray-700">Mulai hari ke-</label>
                    <input id="service-day" type="number" min="1" max="30" wire:model="itemForm.day_no" class="mt-1 w-full rounded-md border-gray-300 text-sm shadow-sm">
                    @error('itemForm.day_no')<p class="text-sm text-red-600">{{ $message }}</p>@enderror
                </div>
                @unless ($serviceInfo['automatic_quantity'])
                    <div>
                        <label for="service-quantity" class="block text-sm font-medium text-gray-700">{{ $serviceInfo['quantity_label'] }}</label>
                        <input id="service-quantity" type="number" min="0.0001" max="9999" step="0.0001" wire:model="itemForm.quantity" class="mt-1 w-full rounded-md border-gray-300 text-sm shadow-sm">
                        @error('itemForm.quantity')<p class="text-sm text-red-600">{{ $message }}</p>@enderror
                    </div>
                @endunless
                @if ($serviceInfo['uses_duration'])
                    <div>
                        <label for="service-duration" class="block text-sm font-medium text-gray-700">Jumlah {{ $serviceInfo['duration_label'] }} layanan</label>
                        <input id="service-duration" type="number" min="1" max="60" wire:model="itemForm.duration_qty" placeholder="Seluruh durasi trip"
                               class="mt-1 w-full rounded-md border-gray-300 text-sm shadow-sm">
                        <p class="mt-1 text-xs text-gray-500">Isi durasi pemakaian, bukan jumlah kamar/kendaraan. Kosongkan untuk mengikuti seluruh durasi trip (mulai hari pertama).</p>
                        @error('itemForm.duration_qty')<p class="text-sm text-red-600">{{ $message }}</p>@enderror
                    </div>
                @endif
            </div>
            <div>
                <label for="service-notes" class="block text-sm font-medium text-gray-700">Catatan layanan</label>
                <input id="service-notes" type="text" wire:model="itemForm.notes" placeholder="Contoh: makan malam hari pertama / penjemputan bandara"
                       class="mt-1 w-full rounded-md border-gray-300 text-sm shadow-sm">
                @error('itemForm.notes')<p class="text-sm text-red-600">{{ $message }}</p>@enderror
            </div>
            <div class="flex flex-wrap justify-end gap-3">
                @if ($editingItemId)
                    <button type="button" wire:click="cancelItemEdit" class="rounded-md border border-gray-300 px-4 py-2 text-sm text-gray-700">Batal edit</button>
                @endif
                <button type="submit" wire:loading.attr="disabled" wire:target="addItem" class="rounded-md bg-gray-800 px-4 py-2 text-sm text-white hover:bg-gray-700">{{ $editingItemId ? 'Simpan layanan' : '+ Tambah layanan' }}</button>
            </div>
        @endif
    </form>
    <div class="flex flex-wrap items-center justify-between gap-3 border-t pt-5">
        <p class="text-xs text-gray-500">Publikasikan setelah rencana dan layanan diperiksa. Trip yang sudah dibuat tidak ikut berubah.</p>
        <button type="button" wire:click="publish" wire:loading.attr="disabled" wire:target="publish"
                wire:confirm="Simpan rencana lalu publikasikan template? Versi ini akan terkunci."
                class="rounded-md bg-green-600 px-4 py-2 text-sm font-medium text-white hover:bg-green-500">Publikasikan template</button>
    </div>
@endif
