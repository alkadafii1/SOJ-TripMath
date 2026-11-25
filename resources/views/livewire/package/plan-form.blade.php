<form wire:submit="saveVersion" class="space-y-6">
    <section class="space-y-4">
        <div>
            <h3 class="text-lg font-semibold text-gray-800">1. Rencana perjalanan</h3>
            <p class="mt-1 text-sm text-gray-500">Durasi ini menjadi default saat membuat trip. Tanggal dan pax tetap ditentukan pada pesanan customer.</p>
        </div>
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
            <div>
                <label for="template-days" class="block text-sm font-medium text-gray-700">Default jumlah hari</label>
                <input id="template-days" type="number" min="1" max="30" wire:model="versionForm.duration_days" @disabled(! $editable)
                       placeholder="Ditentukan saat membuat trip" class="mt-1 w-full rounded-md border-gray-300 text-sm shadow-sm disabled:bg-gray-100">
                @error('versionForm.duration_days')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
            </div>
            <div>
                <label for="template-nights" class="block text-sm font-medium text-gray-700">Default jumlah malam</label>
                <input id="template-nights" type="number" min="0" max="30" wire:model="versionForm.nights" @disabled(! $editable)
                       placeholder="Otomatis: hari dikurangi 1" class="mt-1 w-full rounded-md border-gray-300 text-sm shadow-sm disabled:bg-gray-100">
                @error('versionForm.nights')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
            </div>
            <div>
                <label for="template-effective" class="block text-sm font-medium text-gray-700">Berlaku mulai</label>
                <input id="template-effective" type="date" wire:model="versionForm.effective_date" @disabled(! $editable)
                       class="mt-1 w-full rounded-md border-gray-300 text-sm shadow-sm disabled:bg-gray-100">
                @error('versionForm.effective_date')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
            </div>
        </div>
    </section>

    <section class="space-y-3 border-t pt-5">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <h4 class="font-semibold text-gray-800">Itinerary per hari</h4>
                <p class="text-sm text-gray-500">Tulis kegiatan dan layanan. Kegiatan tanpa biaya tersendiri tidak perlu dibuat sebagai komponen costing.</p>
            </div>
            @if ($editable)
                <button type="button" wire:click="addItineraryDay" class="rounded-md border border-indigo-200 px-3 py-2 text-sm text-indigo-700 hover:bg-indigo-50">+ Hari itinerary</button>
            @endif
        </div>
        @error('versionForm.itinerary_days')<p class="text-sm text-red-600">{{ $message }}</p>@enderror
        @forelse ($versionForm['itinerary_days'] as $index => $day)
            <div wire:key="plan-{{ $versionId }}-{{ $index }}" class="rounded-lg border border-gray-200 bg-gray-50 p-4 space-y-3">
                <div class="grid grid-cols-1 sm:grid-cols-6 gap-3">
                    <div>
                        <label for="plan-day-{{ $index }}" class="block text-xs font-medium text-gray-600">Hari ke-</label>
                        <input id="plan-day-{{ $index }}" type="number" min="1" max="30" wire:model="versionForm.itinerary_days.{{ $index }}.day_no" @disabled(! $editable)
                               class="mt-1 w-full rounded-md border-gray-300 text-sm disabled:bg-gray-100">
                        @error('versionForm.itinerary_days.'.$index.'.day_no')<p class="text-xs text-red-600">{{ $message }}</p>@enderror
                    </div>
                    <div class="sm:col-span-5">
                        <label for="plan-title-{{ $index }}" class="block text-xs font-medium text-gray-600">Judul perjalanan</label>
                        <input id="plan-title-{{ $index }}" type="text" wire:model="versionForm.itinerary_days.{{ $index }}.title" @disabled(! $editable)
                               placeholder="Contoh: Penjemputan dan perjalanan ke Ijen" class="mt-1 w-full rounded-md border-gray-300 text-sm disabled:bg-gray-100">
                        @error('versionForm.itinerary_days.'.$index.'.title')<p class="text-xs text-red-600">{{ $message }}</p>@enderror
                    </div>
                </div>
                <div>
                    <label for="plan-description-{{ $index }}" class="block text-xs font-medium text-gray-600">Kegiatan dan layanan</label>
                    <textarea id="plan-description-{{ $index }}" rows="3" wire:model="versionForm.itinerary_days.{{ $index }}.description" @disabled(! $editable)
                              class="mt-1 w-full rounded-md border-gray-300 text-sm disabled:bg-gray-100"></textarea>
                    @error('versionForm.itinerary_days.'.$index.'.description')<p class="text-xs text-red-600">{{ $message }}</p>@enderror
                </div>
                @if ($editable)
                    <button type="button" wire:click="removeItineraryDay({{ $index }})" class="text-xs text-red-600 hover:underline">Hapus hari itinerary</button>
                @endif
            </div>
        @empty
            <p class="rounded-md bg-gray-50 p-3 text-sm text-gray-500">Belum ada itinerary per hari. Catatan itinerary lama tetap tersedia dan ikut disalin ke trip.</p>
        @endforelse
        <div>
            <label for="template-itinerary" class="block text-sm font-medium text-gray-700">Ringkasan / catatan itinerary</label>
            <textarea id="template-itinerary" wire:model="versionForm.itinerary" rows="3" @disabled(! $editable)
                      class="mt-1 w-full rounded-md border-gray-300 text-sm shadow-sm disabled:bg-gray-100"></textarea>
            @error('versionForm.itinerary')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
        </div>
    </section>

    <section class="grid grid-cols-1 sm:grid-cols-2 gap-4 border-t pt-5">
        <div>
            <label for="template-inclusions" class="block text-sm font-medium text-gray-700">Sudah termasuk</label>
            <textarea id="template-inclusions" wire:model="versionForm.inclusions" rows="4" @disabled(! $editable)
                      placeholder="Layanan yang diterima customer" class="mt-1 w-full rounded-md border-gray-300 text-sm shadow-sm disabled:bg-gray-100"></textarea>
            @error('versionForm.inclusions')<p class="text-sm text-red-600">{{ $message }}</p>@enderror
        </div>
        <div>
            <label for="template-exclusions" class="block text-sm font-medium text-gray-700">Tidak termasuk</label>
            <textarea id="template-exclusions" wire:model="versionForm.exclusions" rows="4" @disabled(! $editable)
                      placeholder="Pengeluaran di luar layanan paket" class="mt-1 w-full rounded-md border-gray-300 text-sm shadow-sm disabled:bg-gray-100"></textarea>
            @error('versionForm.exclusions')<p class="text-sm text-red-600">{{ $message }}</p>@enderror
        </div>
    </section>
    @if ($editable)
        <div class="flex justify-end">
            <button type="submit" wire:loading.attr="disabled" wire:target="saveVersion" class="rounded-md bg-indigo-600 px-4 py-2 text-sm text-white hover:bg-indigo-500">Simpan rencana layanan</button>
        </div>
    @endif
</form>
