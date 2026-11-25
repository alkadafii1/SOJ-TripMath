<div class="mx-auto max-w-7xl space-y-5 px-4 py-8 sm:px-6 lg:px-8">
    <header><h1 class="text-2xl font-semibold text-gray-900">Audit Log</h1><p class="mt-1 text-sm text-gray-500">Riwayat perubahan bersifat hanya-baca. Cari berdasarkan entitas, pengguna, tindakan, dan tanggal.</p></header>
    <div class="grid gap-3 rounded-xl border border-gray-200 bg-white p-5 sm:grid-cols-2 lg:grid-cols-4">
        <label class="text-xs text-gray-600">Entitas<select wire:model.live="entity" class="mt-1 block w-full rounded-md border-gray-300 text-sm"><option value="">Semua entitas</option>@foreach ($entities as $value)<option value="{{ $value }}">{{ $value }}</option>@endforeach</select></label>
        <label class="text-xs text-gray-600">Tindakan<select wire:model.live="action" class="mt-1 block w-full rounded-md border-gray-300 text-sm"><option value="">Semua tindakan</option>@foreach ($actions as $value)<option value="{{ $value }}">{{ $value }}</option>@endforeach</select></label>
        <label class="text-xs text-gray-600">Pengguna<select wire:model.live="actor" class="mt-1 block w-full rounded-md border-gray-300 text-sm"><option value="">Semua pengguna</option>@foreach ($actors as $user)<option value="{{ $user->id }}">{{ $user->name }}</option>@endforeach</select></label>
        <label class="text-xs text-gray-600">ID entitas<input type="number" min="1" wire:model.live.debounce.300ms="recordId" class="mt-1 block w-full rounded-md border-gray-300 text-sm"></label>
        <label class="text-xs text-gray-600">Dari tanggal<input type="date" wire:model.live="from" class="mt-1 block w-full rounded-md border-gray-300 text-sm"></label>
        <label class="text-xs text-gray-600">Sampai tanggal<input type="date" wire:model.live="to" class="mt-1 block w-full rounded-md border-gray-300 text-sm"></label>
    </div>
    @foreach ($filterErrors as $message)<p role="alert" class="text-sm text-red-700">{{ $message }}</p>@endforeach
    <div class="overflow-x-auto rounded-xl border border-gray-200 bg-white">
        <table class="w-full min-w-[760px] text-left text-sm">
            <thead class="bg-gray-50 text-gray-600"><tr><th class="p-4">Waktu / Pengguna</th><th class="p-4">Entitas</th><th class="p-4">Tindakan / Alasan</th><th class="p-4">Perubahan</th></tr></thead>
            <tbody class="divide-y divide-gray-100">
                @forelse ($logs as $log)
                    <tr wire:key="audit-{{ $log->id }}" class="align-top">
                        <td class="p-4"><p>{{ $log->created_at?->format('d/m/Y H:i:s') }}</p><p class="mt-1 text-xs text-gray-500">{{ $log->user?->name ?? 'Sistem / pengguna tidak tersedia' }} / #{{ $log->id }}</p></td>
                        <td class="p-4">{{ $log->entity }} #{{ $log->entity_id ?? '-' }}</td>
                        <td class="p-4"><p class="font-medium">{{ $log->action }}</p><p class="mt-1 max-w-xs break-words text-xs text-gray-500">{{ $log->reason ?? '-' }}</p></td>
                        <td class="p-4"><details><summary class="cursor-pointer text-indigo-700">Lihat sebelum / sesudah</summary>
                            <p class="mt-3 text-xs font-semibold">Sebelum</p><pre class="mt-1 max-w-lg overflow-auto whitespace-pre-wrap break-words text-xs">{{ json_encode($log->old_value, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) }}</pre>
                            <p class="mt-3 text-xs font-semibold">Sesudah</p><pre class="mt-1 max-w-lg overflow-auto whitespace-pre-wrap break-words text-xs">{{ json_encode($log->new_value, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) }}</pre>
                        </details></td>
                    </tr>
                @empty
                    <tr><td colspan="4" class="p-8 text-center text-gray-500">Tidak ada riwayat untuk filter ini.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    {{ $logs->links() }}
</div>
