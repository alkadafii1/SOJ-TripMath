<div class="py-8">
    <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-4">

        {{-- Tab antar entitas --}}
        @if (count($tabs))
            <div class="flex flex-wrap gap-2 border-b border-gray-200 pb-3">
                @foreach ($tabs as $key => [$route, $label])
                    <a href="{{ route($route) }}" wire:navigate
                    class="px-3 py-1.5 rounded-md text-sm font-medium
                            {{ $currentTab === $key ? 'bg-indigo-600 text-white' : 'bg-gray-100 text-gray-700 hover:bg-gray-200' }}">
                        {{ $label }}
                    </a>
                @endforeach
            </div>
        @endif

        <div class="flex items-center justify-between">
            <h2 class="text-xl font-semibold text-gray-800">{{ $heading }}</h2>
            @if ($canManage)
                <button wire:click="create"
                        class="px-4 py-2 bg-indigo-600 text-white text-sm font-medium rounded-md hover:bg-indigo-500">
                    + Tambah
                </button>
            @endif
        </div>

        @if (session('status'))
            <div class="rounded-md bg-green-50 p-3 text-sm text-green-800">{{ session('status') }}</div>
        @endif
        @if (session('error'))
            <div class="rounded-md bg-red-50 p-3 text-sm text-red-800">{{ session('error') }}</div>
        @endif
        @if ($notice)
            <div class="rounded-md border border-amber-200 bg-amber-50 p-3 text-sm text-amber-800">{{ $notice }}</div>
        @endif

        {{-- Pencarian dan filter --}}
        <div class="flex flex-wrap gap-3">
            <input type="search" wire:model.live.debounce.300ms="search" placeholder="Cari..."
                   class="rounded-md border-gray-300 text-sm shadow-sm w-64">
            <select wire:model.live="statusFilter" class="rounded-md border-gray-300 text-sm shadow-sm">
                <option value="">Semua status</option>
                <option value="active">Aktif</option>
                <option value="inactive">Nonaktif</option>
            </select>
        </div>

        {{-- Tabel --}}
        <div class="bg-white shadow sm:rounded-lg overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200 text-sm">
                <thead class="bg-gray-50">
                    <tr>
                        @foreach ($columns as $col)
                            <th class="px-4 py-3 text-left font-medium text-gray-600">{{ $col['label'] }}</th>
                        @endforeach
                        <th class="px-4 py-3"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse ($rows as $row)
                        <tr wire:key="row-{{ $row->id }}">
                            @foreach ($columns as $col)
                                @php($v = data_get($row, $col['key']))
                                <td class="px-4 py-3 {{ ($col['type'] ?? '') === 'money' ? 'text-left' : '' }}">
                                    @switch($col['type'] ?? 'text')
                                        @case('money')
                                            Rp {{ $v !== null ? number_format((float) $v, 0, ',', '.') : '-' }}
                                            @break
                                        @case('percent')
                                            {{ $v !== null ? number_format((float) $v, 2, ',', '.') . '%' : '-' }}
                                            @break
                                        @case('bool')
                                            {{ $v ? 'Ya' : 'Tidak' }}
                                            @break
                                        @case('date')
                                            {{ $v ? \Illuminate\Support\Carbon::parse($v)->format('d/m/Y') : '-' }}
                                            @break
                                        @case('status')
                                            <span class="px-2 py-0.5 rounded-full text-xs
                                                {{ $v === 'active' ? 'bg-green-100 text-green-800' : 'bg-gray-200 text-gray-700' }}">
                                                {{ $v === 'active' ? 'Aktif' : 'Nonaktif' }}
                                            </span>
                                            @break
                                        @default
                                            {{ $v ?? '-' }}
                                    @endswitch
                                </td>
                            @endforeach
                            <td class="px-4 py-3 text-right whitespace-nowrap space-x-3">
                                @if ($detailRoute)
                                    <a href="{{ route($detailRoute, $row->id) }}" wire:navigate class="text-emerald-700 hover:underline">{{ $currentTab === 'package' ? 'Buka template' : 'Kelola versi' }}</a>
                                @endif
                                @if ($canManage)
                                    <button wire:click="edit({{ $row->id }})" class="text-indigo-600 hover:underline">Ubah</button>
                                    <button wire:click="toggleStatus({{ $row->id }})" class="text-gray-600 hover:underline">
                                        {{ $row->status === 'active' ? 'Nonaktifkan' : 'Aktifkan' }}
                                    </button>
                                    <button wire:click="delete({{ $row->id }})"
                                            wire:confirm="Hapus data ini?"
                                            class="text-red-600 hover:underline">Hapus</button>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="{{ count($columns) + 1 }}" class="px-4 py-8 text-center text-gray-500">
                                Belum ada data.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{ $rows->links() }}
    </div>

    {{-- Form tambah/ubah --}}
    @if ($showForm)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4">
            <div class="bg-white rounded-lg shadow-xl w-full max-w-2xl max-h-[90vh] overflow-y-auto p-6">
                <h3 class="text-lg font-semibold text-gray-800">
                    {{ $editingId ? 'Ubah' : 'Tambah' }} {{ $heading }}
                </h3>

                <form wire:submit="save" class="mt-4 grid grid-cols-1 sm:grid-cols-2 gap-4">
                    @foreach ($fields as $f)
                        <div class="{{ $f['type'] === 'textarea' ? 'sm:col-span-2' : '' }}" wire:key="field-{{ $f['name'] }}">
                            <label class="block text-sm font-medium text-gray-700">
                                {{ $f['label'] }}
                                @if (! empty($f['required']))<span class="text-red-500">*</span>@endif
                            </label>

                            @switch($f['type'])
                                @case('textarea')
                                    <textarea wire:model="form.{{ $f['name'] }}" rows="3"
                                              class="mt-1 w-full rounded-md border-gray-300 text-sm shadow-sm"></textarea>
                                    @break
                                @case('select')
                                    <select wire:model="form.{{ $f['name'] }}"
                                            class="mt-1 w-full rounded-md border-gray-300 text-sm shadow-sm">
                                        <option value="">-- pilih --</option>
                                        @foreach ($f['options'] ?? [] as $val => $label)
                                            <option value="{{ $val }}">{{ $label }}</option>
                                        @endforeach
                                    </select>
                                    @break
                                @case('checkbox')
                                    <input type="checkbox" wire:model="form.{{ $f['name'] }}"
                                           class="mt-2 rounded border-gray-300 text-indigo-600 shadow-sm">
                                    @break
                                @case('money')
                                    <x-money-input model="form.{{ $f['name'] }}" class="mt-1" />
                                    @break
                                @case('percent')
                                    <div class="relative mt-1">
                                        <input type="number" step="0.01" min="0" wire:model="form.{{ $f['name'] }}"
                                               class="w-full rounded-md border-gray-300 pr-8 text-sm shadow-sm">
                                        <span class="pointer-events-none absolute inset-y-0 right-0 flex items-center pr-3 text-sm text-gray-500">%</span>
                                    </div>
                                    @break
                                @case('number')
                                    <input type="number" step="1" min="0" wire:model="form.{{ $f['name'] }}"
                                           class="mt-1 w-full rounded-md border-gray-300 text-sm shadow-sm">
                                    @break
                                @case('date')
                                    <input type="date" wire:model="form.{{ $f['name'] }}"
                                           class="mt-1 w-full rounded-md border-gray-300 text-sm shadow-sm">
                                    @break
                                @default
                                    <input type="text" wire:model="form.{{ $f['name'] }}"
                                           class="mt-1 w-full rounded-md border-gray-300 text-sm shadow-sm">
                            @endswitch

                            @if (! empty($f['help']))
                                <p class="mt-1 text-xs text-gray-500">{{ $f['help'] }}</p>
                            @endif
                            @error('form.'.$f['name'])
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>
                    @endforeach

                    @if ($editingId)
                        <div class="sm:col-span-2">
                            <label class="block text-sm font-medium text-gray-700">Alasan perubahan</label>
                            <textarea wire:model="reason" rows="2"
                                      class="mt-1 w-full rounded-md border-gray-300 text-sm shadow-sm"></textarea>
                            @if ($rateLabels)
                                <p class="mt-1 text-xs text-gray-500">Wajib diisi bila mengubah: {{ $rateLabels }}.</p>
                            @endif
                            @error('reason')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>
                    @endif

                    <div class="sm:col-span-2 flex justify-end gap-3 pt-2">
                        <button type="button" wire:click="closeForm"
                                class="px-4 py-2 text-sm rounded-md border border-gray-300 text-gray-700 hover:bg-gray-50">
                            Batal
                        </button>
                        <button type="submit"
                                class="px-4 py-2 text-sm rounded-md bg-indigo-600 text-white hover:bg-indigo-500">
                            Simpan
                        </button>
                    </div>
                </form>
            </div>
        </div>
    @endif
</div>