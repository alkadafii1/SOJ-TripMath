<?php

namespace App\Services;

use App\Models\Trip;

class TripCostService
{
    private const DURATION_TYPES = ['per_room', 'per_night', 'per_day'];

    public function __construct(private TripCalculator $calculator) {}

    private function calcItems(iterable $items): array
    {
        return collect($items)
            ->mapWithKeys(fn ($item) => [$item->id => $item->toCalcArray()])
            ->all();
    }

    /** Hasil hitungan tanpa menyimpan apa pun (untuk ditampilkan). */
    public function summary(Trip $trip, string $field = 'standard_cost', ?string $revenue = null, ?iterable $items = null): array
    {
        $items ??= $trip->costItems()->active()->get();

        return $this->calculator->calculateTripCost($trip->effectivePax(), $this->calcItems($items), $field, $revenue);
    }

    /** Hitung ulang lalu simpan calculated_quantity tiap item. */
    public function recalculate(Trip $trip): array
    {
        $items = $trip->costItems()->active()->get();
        $result = $this->summary($trip, items: $items);

        foreach ($items as $item) {
            $item->update([
                'calculated_quantity' => $result['lines'][$item->id]['calculated_quantity'] ?? null,
            ]);
        }

        return $result;
    }

    /** Dipanggil saat tanggal trip berubah: durasi malam/hari ikut diperbarui. */
    public function refreshDurations(Trip $trip): void
    {
        foreach ($trip->costItems()->active()->where('follows_trip_duration', true)->whereIn('cost_type', self::DURATION_TYPES)->get() as $item) {
            $item->update(['duration_qty' => TripItemFactory::defaultDuration($item->cost_type, $trip)]);
        }
    }
}
