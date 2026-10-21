<div class="py-8">
    <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">

        <div>
            <a href="{{ route('packages') }}" wire:navigate class="text-sm text-indigo-600 hover:underline">&larr; Semua paket</a>
            <h2 class="text-xl font-semibold text-gray-800 mt-1">{{ $package->name }}</h2>
            <p class="text-sm text-gray-500">
                {{ \App\Support\Options::TRIP_TYPES[$package->trip_type] ?? $package->trip_type }}
            </p>
        </div>

        @if (session('status'))
            <div class="rounded-md bg-green-50 p-3 text-sm text-green-800">{{ session('status') }}</div>
        @endif
        @if (session('error'))
            <div class="rounded-md bg-red-50 p-3 text-sm text-red-800">{{ session('error') }}</div>
        @endif

        {{-- Daftar versi --}}
        <div class="bg-white shadow sm:rounded-lg p-4 space-y-3">
            <div class="flex flex-wrap gap-2">
                @forelse ($versions as $v)
                    <button wire:click="selectVersion({{ $v->id }})" wire:key="ver-{{ $v->id }}"
                            class="px-3 py-1.5 rounded-md text-sm border
                                   {{ $versionId === $v->id ? 'bg-indigo-600 text-white border-indigo-600' : 'bg-white text-gray-700 border-gray-300 hover:bg-gray-50' }}">
                        Versi {{ $v->version_no }}
                        <span class="text-xs opacity-80">({{ \App\Support\Options::VERSION_STATUS[$v->status] ?? $v->status }})</span>
                    </button>
                @empty
                    <p class="text-sm text-gray-500">Belum ada versi.</p>
                @endforelse
            </div>

            @if ($canManage && ! $hasDraft)
                <div class="flex flex-wrap items-start gap-3 border-t pt-3">
                    @if ($versions->isNotEmpty())
                        <div>
                            <input type="text" wire:model="changeReason" placeholder="Alasan membuat versi baru"
                                   class="rounded-md border-gray-300 text-sm shadow-sm w-72">
                            @error('changeReason')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>
                    @endif
                    <button wire:click="createVersion"
                            class="px-4 py-2 bg-indigo-600 text-white text-sm font-medium rounded-md hover:bg-indigo-500">
                        {{ $versions->isEmpty() ? 'Buat versi pertama' : 'Buat versi baru' }}
                    </button>
                </div>
            @endif
        </div>

        @if ($version)
            {{-- Isi versi --}}
            <div class="bg-white shadow sm:rounded-lg p-6 space-y-4">
                <div class="flex flex-wrap items-center justify-between gap-2">
                    <h3 class="text-lg font-semibold text-gray-800">
                        Versi {{ $version->version_no }}
                        <span class="ml-2 px-2 py-0.5 rounded-full text-xs
                            {{ $version->status === 'active' ? 'bg-green-100 text-green-800' : ($version->status === 'draft' ? 'bg-yellow-100 text-yellow-800' : 'bg-gray-200 text-gray-700') }}">
                            {{ \App\Support\Options::VERSION_STATUS[$version->status] ?? $version->status }}
                        </span>
                    </h3>
                    @unless ($editable)
                        <p class="text-xs text-gray-500">Terkunci. Buat versi baru untuk mengubah isinya.</p>
                    @endunless
                </div>

                @if ($version->change_log)
                    <p class="text-sm text-gray-600"><span class="font-medium">Catatan perubahan:</span> {{ $version->change_log }}</p>
                @endif

                <form wire:submit="saveVersion" class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Tanggal efektif</label>
                        <input type="date" wire:model="versionForm.effective_date" @disabled(! $editable)
                               class="mt-1 w-full rounded-md border-gray-300 text-sm shadow-sm disabled:bg-gray-100">
                        @error('versionForm.effective_date')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                    </div>
                    <div class="sm:col-span-2">
                        <label class="block text-sm font-medium text-gray-700">Itinerary</label>
                        <textarea wire:model="versionForm.itinerary" rows="6" @disabled(! $editable)
                                  class="mt-1 w-full rounded-md border-gray-300 text-sm shadow-sm disabled:bg-gray-100"></textarea>
                        @error('versionForm.itinerary')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Inclusions (sudah termasuk)</label>
                        <textarea wire:model="versionForm.inclusions" rows="4" @disabled(! $editable)
                                  class="mt-1 w-full rounded-md border-gray-300 text-sm shadow-sm disabled:bg-gray-100"></textarea>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Exclusions (tidak termasuk)</label>
                        <textarea wire:model="versionForm.exclusions" rows="4" @disabled(! $editable)
                                  class="mt-1 w-full rounded-md border-gray-300 text-sm shadow-sm disabled:bg-gray-100"></textarea>
                    </div>

                    @if ($editable)
                        <div class="sm:col-span-2 flex justify-end">
                            <button type="submit"
                                    class="px-4 py-2 text-sm rounded-md bg-indigo-600 text-white hover:bg-indigo-500">
                                Simpan isi versi
                            </button>
                        </div>
                    @endif
                </form>
            </div>

            {{-- Komponen --}}
            <div class="bg-white shadow sm:rounded-lg p-6 space-y-4">
                <h3 class="text-lg font-semibold text-gray-800">Komponen paket</h3>

                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 text-sm">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-4 py-2 text-left font-medium text-gray-600">Hari</th>
                                <th class="px-4 py-2 text-left font-medium text-gray-600">Jenis</th>
                                <th class="px-4 py-2 text-left font-medium text-gray-600">Komponen</th>
                                <th class="px-4 py-2 text-right font-medium text-gray-600">Jumlah</th>
                                <th class="px-4 py-2 text-left font-medium text-gray-600">Catatan</th>
                                <th class="px-4 py-2"></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            @forelse ($items as $item)
                                <tr wire:key="item-{{ $item->id }}">
                                    <td class="px-4 py-2">{{ $item->day_no ?? '-' }}</td>
                                    <td class="px-4 py-2">{{ \App\Support\Options::ITEM_TYPES[$item->item_type] ?? $item->item_type }}</td>
                                    <td class="px-4 py-2">{{ $item->item_label }}</td>
                                    <td class="px-4 py-2 text-right">{{ rtrim(rtrim(number_format((float) $item->quantity, 4, ',', '.'), '0'), ',') }}</td>
                                    <td class="px-4 py-2">{{ $item->notes ?? '-' }}</td>
                                    <td class="px-4 py-2 text-right">
                                        @if ($editable)
                                            <button wire:click="removeItem({{ $item->id }})"
                                                    wire:confirm="Hapus komponen ini?"
                                                    class="text-red-600 hover:underline">Hapus</button>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="6" class="px-4 py-6 text-center text-gray-500">Belum ada komponen.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                @if ($editable)
                    <form wire:submit="addItem" class="grid grid-cols-1 sm:grid-cols-6 gap-3 border-t pt-4">
                        <div class="sm:col-span-1">
                            <label class="block text-xs font-medium text-gray-600">Jenis</label>
                            <select wire:model.live="itemForm.item_type"
                                    class="mt-1 w-full rounded-md border-gray-300 text-sm shadow-sm">
                                <option value="">-- pilih --</option>
                                @foreach (\App\Support\Options::ITEM_TYPES as $val => $label)
                                    <option value="{{ $val }}">{{ $label }}</option>
                                @endforeach
                            </select>
                            @error('itemForm.item_type')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                        </div>
                        <div class="sm:col-span-2">
                            <label class="block text-xs font-medium text-gray-600">Komponen</label>
                            <select wire:model="itemForm.item_id"
                                    class="mt-1 w-full rounded-md border-gray-300 text-sm shadow-sm">
                                <option value="">-- pilih --</option>
                                @foreach ($choices as $id => $label)
                                    <option value="{{ $id }}">{{ $label }}</option>
                                @endforeach
                            </select>
                            @error('itemForm.item_id')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-gray-600">Jumlah</label>
                            <input type="number" step="0.0001" min="0" wire:model="itemForm.quantity"
                                   class="mt-1 w-full rounded-md border-gray-300 text-sm shadow-sm">
                            @error('itemForm.quantity')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-gray-600">Hari ke-</label>
                            <input type="number" min="1" max="30" wire:model="itemForm.day_no"
                                   class="mt-1 w-full rounded-md border-gray-300 text-sm shadow-sm">
                            @error('itemForm.day_no')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                        </div>
                        <div class="flex items-end">
                            <button type="submit"
                                    class="w-full px-3 py-2 text-sm rounded-md bg-gray-800 text-white hover:bg-gray-700">
                                + Tambah
                            </button>
                        </div>
                        <div class="sm:col-span-6">
                            <input type="text" wire:model="itemForm.notes" placeholder="Catatan (opsional)"
                                   class="w-full rounded-md border-gray-300 text-sm shadow-sm">
                        </div>
                    </form>

                    <div class="flex justify-end border-t pt-4">
                        <button wire:click="publish"
                                wire:confirm="Setelah dipublikasikan, versi ini terkunci. Lanjutkan?"
                                class="px-4 py-2 text-sm font-medium rounded-md bg-green-600 text-white hover:bg-green-500">
                            Publikasikan versi ini
                        </button>
                    </div>
                @endif
            </div>
        @endif
    </div>
</div>