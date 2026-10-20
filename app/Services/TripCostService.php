<?php

namespace App\Services;

use App\Models\Trip;

class TripCostService
{
    private const DURATION_TYPES = ['per_room', 'per_night', 'per_day'];

    public function __construct(private TripCalculator $calculator)
    {
    }

    private function calcItems(Trip $trip): array
    {
        return $trip->costItems()->active()->get()
            ->mapWithKeys(fn ($item) => [$item->id => $item->toCalcArray()])
            ->all();
    }

    /** Hasil hitungan tanpa menyimpan apa pun (untuk ditampilkan). */
    public function summary(Trip $trip, string $field = 'standard_cost', ?string $revenue = null): array
    {
        return $this->calculator->calculateTripCost((int) $trip->pax, $this->calcItems($trip), $field, $revenue);
    }

    /** Hitung ulang lalu simpan calculated_quantity tiap item. */
    public function recalculate(Trip $trip): array
    {
        $result = $this->summary($trip);

        foreach ($trip->costItems()->active()->get() as $item) {
            $item->update([
                'calculated_quantity' => $result['lines'][$item->id]['calculated_quantity'] ?? null,
            ]);
        }

        return $result;
    }

    /** Dipanggil saat tanggal trip berubah: durasi malam/hari ikut diperbarui. */
    public function refreshDurations(Trip $trip): void
    {
        foreach ($trip->costItems()->active()->whereIn('cost_type', self::DURATION_TYPES)->get() as $item) {
            $item->update(['duration_qty' => TripItemFactory::defaultDuration($item->cost_type, $trip)]);
        }
    }
}