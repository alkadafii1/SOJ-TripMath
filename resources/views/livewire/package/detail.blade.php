

<div class="py-8">
    <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">

        <div>
            <a href="{{ route('packages') }}" wire:navigate class="text-sm text-indigo-600 hover:underline">&larr; Semua paket</a>
            <h2 class="text-xl font-semibold text-gray-800 mt-1">{{ $package->name }}</h2>
            <p class="text-sm text-gray-500">
                {{ \App\Support\Options::TRIP_TYPES[$package->trip_type] ?? $package->trip_type }}
            </p>
            <p class="mt-2 max-w-3xl text-sm text-gray-600">{{ $package->description ?: 'Template layanan perjalanan yang dapat digunakan ulang untuk pesanan customer.' }}</p>
            <p class="mt-2 text-xs text-gray-500">Master layanan &rarr; Template perjalanan &rarr; Trip customer &rarr; Costing &amp; pricing &rarr; Rekonsiliasi</p>
        </div>

        @if (session('status'))
            <div class="rounded-md bg-green-50 p-3 text-sm text-green-800">{{ session('status') }}</div>
        @endif
        @if (session('error'))
            <div class="rounded-md bg-red-50 p-3 text-sm text-red-800">{{ session('error') }}</div>
        @endif

        {{-- Daftar versi --}}
                <details class="bg-white shadow sm:rounded-lg p-4 space-y-3" @if (! $version) open @endif>
            <summary class="cursor-pointer text-sm font-medium text-gray-700">Versi &amp; riwayat template{{ $version ? ' - versi '.$version->version_no : '' }}</summary>
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
                </details>

        @if ($version)
            <section class="rounded-lg border border-indigo-100 bg-indigo-50 p-5 space-y-3">
                <div class="flex flex-wrap items-center justify-between gap-4">
                    <div>
                        <h3 class="font-semibold text-gray-800">Template layanan, bukan transaksi customer</h3>
                        <p class="mt-1 text-sm text-gray-600">{{ $version->duration_days ? $version->duration_days.' hari / '.($version->nights ?? max($version->duration_days - 1, 0)).' malam' : 'Durasi diatur saat membuat trip' }} &middot; {{ $items->count() }} layanan default</p>
                    </div>
                    @if ($version->status === 'active' && $package->status === 'active')
                        @can('trip.create')
                            <a href="{{ route('trips.create', ['template' => $version->id]) }}" wire:navigate class="inline-flex rounded-md bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-500">Gunakan template untuk trip customer</a>
                        @endcan
                    @else
                        <p class="text-sm text-amber-800">{{ $version->status === 'draft' ? 'Lengkapi dan publikasikan template sebelum dipakai.' : 'Versi arsip/paket nonaktif tidak dapat digunakan untuk trip baru.' }}</p>
                    @endif
                </div>
                <p class="text-xs text-gray-600">Customer, pax, dan tanggal diisi pada trip. Standard Cost disalin dari master; negosiasi vendor, harga jual, pembayaran, dan Actual Cost dikelola terpisah pada trip.</p>
                <div class="flex flex-wrap gap-2">
                    @foreach ($items->where('item_type', 'destination')->unique('item_id') as $destination)
                        <span class="rounded-full bg-white px-3 py-1 text-xs text-indigo-700">{{ $destination->item_label }}</span>
                    @endforeach
                </div>
            </section>
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
                        <p class="text-xs text-gray-500">{{ $version->status === 'draft' ? 'Akses baca saja. Perubahan template memerlukan izin pengelolaan paket.' : 'Terkunci. Buat versi baru untuk mengubah isinya.' }}</p>
                    @endunless
                </div>

                @if ($version->change_log)
                    <p class="text-sm text-gray-600"><span class="font-medium">Catatan perubahan:</span> {{ $version->change_log }}</p>
                @endif

                                @include('livewire.package.plan-form')
            </div>

            {{-- Komponen --}}
            <div class="bg-white shadow sm:rounded-lg p-6 space-y-4">
                                <h3 class="text-lg font-semibold text-gray-800">2. Default layanan &amp; costing internal</h3>
                <p class="text-sm text-gray-500">Hubungkan layanan itinerary dengan master. Tarif yang berlaku diperiksa saat trip dibuat; tidak ada harga jual final atau profit customer pada template ini.</p>
                @error('packageVersionId')<p role="alert" class="text-sm text-red-600">{{ $message }}</p>@enderror

                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 text-sm">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-4 py-2 text-left font-medium text-gray-600">Hari mulai</th>
                                <th class="px-4 py-2 text-left font-medium text-gray-600">Jenis</th>
                                <th class="px-4 py-2 text-left font-medium text-gray-600">Komponen</th>
                                <th class="px-4 py-2 text-right font-medium text-gray-600">Jumlah</th>
                                <th class="px-4 py-2 text-left font-medium text-gray-600">Dasar biaya / kapasitas</th>
                                <th class="px-4 py-2 text-left font-medium text-gray-600">Durasi layanan</th>
                                <th class="px-4 py-2 text-left font-medium text-gray-600">Catatan</th>
                                <th class="px-4 py-2"></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            @forelse ($items as $item)
                                @php
                                    $master = $item->item;
                                    $info = $master ? \App\Services\TripItemFactory::templateInfo($item->item_type, $master) : null;
                                    $costType = $info['cost_type'] ?? '';
                                    $capacity = $info['unit_capacity'] ?? null;
                                    $vendor = $master && method_exists($master, 'vendor') ? $master->vendor?->name : null;
                                @endphp
                                <tr wire:key="item-{{ $item->id }}">
                                    <td class="px-4 py-2">{{ $item->day_no ?? '-' }}</td>
                                    <td class="px-4 py-2">{{ \App\Support\Options::ITEM_TYPES[$item->item_type] ?? $item->item_type }}</td>
                                                                        <td class="px-4 py-2">
                                        {{ $item->item_label }}
                                        @if ($vendor)<span class="block text-xs text-gray-500">{{ $vendor }}</span>@endif
                                        @if ($showMoney && $info)
                                            <span class="block text-xs text-gray-500">Tarif referensi: {{ $costType === 'percentage' ? $info['percentage'].'%' : \App\Support\Money::rp($info['standard_cost']) }}</span>
                                        @endif
                                    </td>
                                    <td class="px-4 py-2 text-right">{{ ($info['automatic_quantity'] ?? false) ? ($costType === 'per_person' ? 'Seluruh peserta' : 'Otomatis') : rtrim(rtrim(number_format((float) $item->quantity, 4, ',', '.'), '0'), ',') }}</td>
                                    
                                    <td class="px-4 py-2">
                                        {{ \App\Support\Options::COST_TYPES[$costType] ?? '-' }}
                                        @if ($capacity) <span class="block text-xs text-gray-500">{{ $capacity }} orang/unit</span> @endif
                                        @if (! $master || $master->trashed() || $master->status !== 'active')
                                            <span class="block text-xs text-red-600">Tidak tersedia</span>
                                        @endif
                                    </td>
                                    
                                    <td class="px-4 py-2">
                                        @if (in_array($costType, \App\Services\TripItemFactory::DURATION_TYPES, true))
                                            {{ $item->duration_qty ?? 'Ikuti trip' }} {{ $item->duration_qty ? (in_array($costType, ['per_room', 'per_night']) ? 'malam' : 'hari') : '' }}
                                        @else
                                            Satu layanan
                                        @endif
                                    </td>
                                    
                                    <td class="px-4 py-2">{{ $item->notes ?? '-' }}</td>
                                    <td class="px-4 py-2 text-right">
                                        @if ($editable)
                                            <button type="button" wire:click="editItem({{ $item->id }})" class="mr-3 text-indigo-600 hover:underline">Edit</button>
                                            <button wire:click="removeItem({{ $item->id }})"
                                                    wire:confirm="Hapus komponen ini?"
                                                    class="text-red-600 hover:underline">Hapus</button>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="8" class="px-4 py-6 text-center text-gray-500">Belum ada komponen.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                                @include('livewire.package.service-form')
            </div>
        @endif
    </div>
</div>