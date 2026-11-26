<?php

namespace App\Services;

use App\Models\PackageVersion;
use App\Models\Trip;

class TripService
{
    /** Kode berurutan per tahun: SOJ-2026-0001. Panggil di dalam transaksi. */
    public function nextCode(): string
    {
        $prefix = 'SOJ-'.now()->year.'-';

        $last = Trip::withTrashed()
            ->where('trip_code', 'like', $prefix.'%')
            ->lockForUpdate()
            ->orderByDesc('trip_code')
            ->value('trip_code');

        $next = $last ? ((int) substr($last, strlen($prefix))) + 1 : 1;

        return $prefix.str_pad((string) $next, 4, '0', STR_PAD_LEFT);
    }

    /** Menyalin komponen paket ke trip. Tarif diambil dari master saat ini. */
    public function snapshotFromVersion(Trip $trip, PackageVersion $version): void
    {
        app(TemplateReadiness::class)->validate($version, $trip);
        foreach ($version->items()->with('item')->orderBy('day_no')->orderBy('id')->get() as $row) {
            $master = $row->item;
            $attributes = TripItemFactory::make($row->item_type, $master, $trip);
            if ($row->duration_qty !== null && in_array($attributes['cost_type'], TripItemFactory::DURATION_TYPES, true)) {
                $attributes['duration_qty'] = $row->duration_qty;
            }
            $trip->costItems()->create($attributes + [
                'day_no' => $row->day_no,
                'follows_trip_duration' => $row->duration_qty === null,
                'quantity' => $row->quantity,
                'notes' => $row->notes,
            ]);
        }
    }

    public function destinationIdsFromVersion(PackageVersion $version): array
    {
        return $version->items()->where('item_type', 'destination')
            ->pluck('item_id')->map(fn ($id) => (int) $id)->all();
    }

    public function syncDestinations(Trip $trip, array $ids): void
    {
        $sync = [];
        foreach (array_values(array_unique(array_map('intval', $ids))) as $i => $id) {
            $sync[$id] = ['sort_order' => $i];
        }
        $trip->destinations()->sync($sync);
    }
}
