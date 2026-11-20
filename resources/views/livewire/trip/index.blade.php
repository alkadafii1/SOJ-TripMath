<div class="py-8">
    <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-4">
        <div class="flex items-center justify-between">
            <h2 class="text-xl font-semibold text-gray-800">Trip</h2>
            @can('trip.create')
                <a href="{{ route('trips.create') }}" wire:navigate
                   class="px-4 py-2 bg-indigo-600 text-white text-sm font-medium rounded-md hover:bg-indigo-500">+ Trip baru</a>
            @endcan
        </div>

        @if (session('status'))
            <div class="rounded-md bg-green-50 p-3 text-sm text-green-800">{{ session('status') }}</div>
        @endif

        @if ($statuses !== '' || $startFrom !== '' || $startTo !== '')
            <div class="flex flex-wrap items-center justify-between gap-2 rounded-lg bg-indigo-50 p-3 text-xs text-indigo-800">
                <span>Filter dashboard: {{ $startFrom ?: '-' }} s/d {{ $startTo ?: '-' }}
                    @if ($statuses) / {{ collect(explode(',', $statuses))->map(fn ($s) => \App\Models\Trip::STATUS[$s] ?? $s)->implode(', ') }} @endif
                </span>
                <a href="{{ route('trips.index') }}" wire:navigate class="font-semibold underline">Hapus filter dashboard</a>
            </div>
        @endif

        <div class="flex flex-wrap gap-3">
            <input type="search" wire:model.live.debounce.300ms="search" placeholder="Cari kode, nama trip, atau customer..."
                   class="rounded-md border-gray-300 text-sm shadow-sm w-80">
            <select wire:model.live="statusFilter" class="rounded-md border-gray-300 text-sm shadow-sm">
                <option value="">Semua status</option>
                @foreach (\App\Models\Trip::STATUS as $val => $label)
                    <option value="{{ $val }}">{{ $label }}</option>
                @endforeach
            </select>
        </div>

        <div class="bg-white shadow sm:rounded-lg overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200 text-sm">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-4 py-3 text-left font-medium text-gray-600">Kode</th>
                        <th class="px-4 py-3 text-left font-medium text-gray-600">Trip</th>
                        <th class="px-4 py-3 text-left font-medium text-gray-600">Customer</th>
                        <th class="px-4 py-3 text-left font-medium text-gray-600">Tipe</th>
                        <th class="px-4 py-3 text-left font-medium text-gray-600">Tanggal</th>
                        <th class="px-4 py-3 text-right font-medium text-gray-600">Pax</th>
                        <th class="px-4 py-3 text-left font-medium text-gray-600">Status</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse ($trips as $t)
                        <tr wire:key="trip-{{ $t->id }}">
                            <td class="px-4 py-3">
                                <a href="{{ route('trips.show', $t->id) }}" wire:navigate class="text-indigo-600 hover:underline">{{ $t->trip_code }}</a>
                            </td>
                            <td class="px-4 py-3">{{ $t->trip_name }}</td>
                            <td class="px-4 py-3">{{ $t->customer_name }}</td>
                            <td class="px-4 py-3">{{ \App\Support\Options::TRIP_TYPES[$t->trip_type] ?? $t->trip_type }}</td>
                            <td class="px-4 py-3">{{ $t->start_date->format('d/m/Y') }} - {{ $t->end_date->format('d/m/Y') }}</td>
                            <td class="px-4 py-3 text-right">{{ $t->pax }}</td>
                            <td class="px-4 py-3">
                                <span class="px-2 py-0.5 rounded-full text-xs {{ $t->statusBadgeClass() }}">
                                    {{ \App\Models\Trip::STATUS[$t->status] ?? $t->status }}
                                </span>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="px-4 py-8 text-center text-gray-500">Belum ada trip.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{ $trips->links() }}
    </div>
</div>