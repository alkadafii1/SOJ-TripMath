<div class="py-8">
    <div class="max-w-3xl mx-auto sm:px-6 lg:px-8 space-y-4">
        <a href="{{ $isEdit ? route('trips.show', $trip->id) : route('trips.index') }}" wire:navigate
           class="text-sm text-indigo-600 hover:underline">&larr; Kembali</a>
        <h2 class="text-xl font-semibold text-gray-800">{{ $isEdit ? 'Ubah trip '.$trip->trip_code : 'Trip baru' }}</h2>

        <form wire:submit="save" class="bg-white shadow sm:rounded-lg p-6 grid grid-cols-1 sm:grid-cols-2 gap-4">

            @unless ($isEdit)
                <div class="sm:col-span-2">
                    <label class="block text-sm font-medium text-gray-700">Mulai dari paket (opsional)</label>
                    <select wire:model="packageVersionId" class="mt-1 w-full rounded-md border-gray-300 text-sm shadow-sm">
                        <option value="">-- trip kosong / custom --</option>
                        @foreach ($versionOptions as $id => $label)
                            <option value="{{ $id }}">{{ $label }}</option>
                        @endforeach
                    </select>
                    <p class="mt-1 text-xs text-gray-500">Komponen paket disalin ke trip dengan tarif saat ini.</p>
                    @error('packageVersionId')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                </div>
            @endunless

            <div class="sm:col-span-2">
                <label class="block text-sm font-medium text-gray-700">Nama trip <span class="text-red-500">*</span></label>
                <input type="text" wire:model="form.trip_name" class="mt-1 w-full rounded-md border-gray-300 text-sm shadow-sm">
                @error('form.trip_name')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700">Customer <span class="text-red-500">*</span></label>
                <input type="text" wire:model="form.customer_name" class="mt-1 w-full rounded-md border-gray-300 text-sm shadow-sm">
                @error('form.customer_name')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700">Kontak customer</label>
                <input type="text" wire:model="form.customer_contact" class="mt-1 w-full rounded-md border-gray-300 text-sm shadow-sm">
                @error('form.customer_contact')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700">Tipe trip <span class="text-red-500">*</span></label>
                <select wire:model.live="form.trip_type" class="mt-1 w-full rounded-md border-gray-300 text-sm shadow-sm">
                    @foreach (\App\Support\Options::TRIP_TYPES as $val => $label)
                        <option value="{{ $val }}">{{ $label }}</option>
                    @endforeach
                </select>
                @error('form.trip_type')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700">Pax <span class="text-red-500">*</span></label>
                <input type="number" min="1" wire:model="form.pax" class="mt-1 w-full rounded-md border-gray-300 text-sm shadow-sm">
                @error('form.pax')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700">Tanggal mulai <span class="text-red-500">*</span></label>
                {{-- Diubah menjadi wire:model.live --}}
                <input type="date" wire:model.live="form.start_date" class="mt-1 w-full rounded-md border-gray-300 text-sm shadow-sm">
                @error('form.start_date')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700">Tanggal selesai <span class="text-red-500">*</span></label>
                {{-- Diubah menjadi wire:model.live --}}
                <input type="date" wire:model.live="form.end_date" class="mt-1 w-full rounded-md border-gray-300 text-sm shadow-sm">
                @error('form.end_date')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
            </div>

            {{-- Tambahan Blok Jumlah Malam --}}
            <div class="sm:col-span-2">
                <label class="block text-sm font-medium text-gray-700">Jumlah malam</label>
                <input type="number" min="0" max="60" wire:model="form.nights"
                       placeholder="Otomatis"
                       class="mt-1 w-full rounded-md border-gray-300 text-sm shadow-sm">
                <p class="mt-1 text-xs text-gray-500">
                    {{ (! empty($form['start_date']) && ! empty($form['end_date']))
                        ? 'Kosongkan untuk otomatis: '.rescue(fn () => max(\App\Models\Trip::daysBetween($form['start_date'], $form['end_date']) - 1, 0), '-').' malam. Isi sendiri bila berbeda, misalnya trip 1 hari 1 malam.'
                        : 'Kosongkan untuk otomatis (jumlah hari dikurangi 1). Isi sendiri bila berbeda, misalnya trip 1 hari 1 malam.' }}
                </p>
                @error('form.nights')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
            </div>

            @if (($form['trip_type'] ?? '') === 'open_trip')
                <div>
                    <label class="block text-sm font-medium text-gray-700">Minimum pax</label>
                    <input type="number" min="1" wire:model="form.min_pax" class="mt-1 w-full rounded-md border-gray-300 text-sm shadow-sm">
                    @error('form.min_pax')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700">Kapasitas maksimum</label>
                    <input type="number" min="1" wire:model="form.max_capacity" class="mt-1 w-full rounded-md border-gray-300 text-sm shadow-sm">
                    @error('form.max_capacity')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700">Confirmed pax</label>
                    <input type="number" min="0" wire:model="form.confirmed_pax" class="mt-1 w-full rounded-md border-gray-300 text-sm shadow-sm">
                    @error('form.confirmed_pax')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                </div>
            @endif

            <div>
                <label class="block text-sm font-medium text-gray-700">Staff yang ditugaskan</label>
                <select wire:model="form.assigned_staff_id" class="mt-1 w-full rounded-md border-gray-300 text-sm shadow-sm">
                    <option value="">-- belum ditentukan --</option>
                    @foreach ($staffOptions as $id => $name)
                        <option value="{{ $id }}">{{ $name }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700">Operational Partner</label>
                <select wire:model="form.assigned_partner_id" class="mt-1 w-full rounded-md border-gray-300 text-sm shadow-sm">
                    <option value="">-- belum ditentukan --</option>
                    @foreach ($partnerOptions as $id => $name)
                        <option value="{{ $id }}">{{ $name }}</option>
                    @endforeach
                </select>
            </div>

            <div class="sm:col-span-2">
                <label class="block text-sm font-medium text-gray-700">Destinasi</label>
                <div class="mt-1 grid grid-cols-2 sm:grid-cols-3 gap-2">
                    @foreach ($destinationOptions as $id => $name)
                        <label class="flex items-center gap-2 text-sm text-gray-700">
                            <input type="checkbox" wire:model="destinationIds" value="{{ $id }}"
                                   class="rounded border-gray-300 text-indigo-600 shadow-sm">
                            {{ $name }}
                        </label>
                    @endforeach
                </div>
                @error('destinationIds.*')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
            </div>

            <div class="sm:col-span-2">
                <label class="block text-sm font-medium text-gray-700">Catatan internal</label>
                <textarea wire:model="form.notes" rows="3" class="mt-1 w-full rounded-md border-gray-300 text-sm shadow-sm"></textarea>
                @error('form.notes')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
            </div>

            <div class="sm:col-span-2 flex justify-end">
                <button type="submit" class="px-4 py-2 text-sm rounded-md bg-indigo-600 text-white hover:bg-indigo-500">
                    Simpan
                </button>
            </div>
        </form>
    </div>
</div>