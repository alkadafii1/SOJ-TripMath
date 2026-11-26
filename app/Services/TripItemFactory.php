<?php

namespace App\Services;

use App\Models\PackageVersionItem;
use App\Models\Trip;
use App\Support\Options;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;

class TripItemFactory
{
    /** Tipe yang jumlah unitnya bisa dihitung dari kapasitas (kamar, kendaraan). */
    public const UNIT_TYPES = ['per_room', 'per_vehicle', 'per_day', 'per_night'];

    /** Tipe yang dikalikan jumlah malam atau hari. */
    public const DURATION_TYPES = ['per_room', 'per_night', 'per_day'];

    public static function make(string $type, Model $m, Trip $trip): array
    {
        $attributes = self::masterAttributes($type, $m);

        return $attributes + ['duration_qty' => self::defaultDuration($attributes['cost_type'], $trip)];
    }

    public static function masterAttributes(string $type, Model $m): array
    {
        [$category, $costType, $rate, $capacity] = match ($type) {
            'hotel' => ['Akomodasi', $m->cost_type ?: 'per_room', $m->standard_rate, $m->occupancy],
            'vehicle' => ['Transportasi', $m->cost_type ?: 'per_day', $m->standard_cost, $m->capacity],
            'activity' => ['Aktivitas', $m->cost_type ?: 'per_person', $m->standard_cost, $m->capacity],
            'meal' => ['Makan', 'per_person', $m->cost_per_person, null],
            'destination' => ['Tiket destinasi', 'per_person', $m->standard_cost, null],
            'cost_item' => [Options::COST_CATEGORIES[$m->category] ?? ucfirst($m->category), $m->cost_type, $m->standard_cost, null],
            default => throw new InvalidArgumentException("Jenis komponen tidak dikenal: {$type}"),
        };

        $capacity = $capacity ? (int) $capacity : null;
        $isCostItem = $type === 'cost_item';
        $hasUnits = $capacity !== null && in_array($costType, self::UNIT_TYPES, true);

        return [
            'item_type' => $type,
            'item_id' => $m->id,
            'vendor_id' => $m->vendor_id ?? null,
            'category' => $category,
            'item_name' => PackageVersionItem::labelFor($type, $m),
            'cost_type' => $costType,
            'cost_behavior' => self::behaviorFor($costType, $hasUnits, null),

            'unit_capacity' => $capacity,
            'percentage' => $isCostItem ? $m->percentage : null,
            'base_type' => $isCostItem ? $m->base_type : null,
            'standard_cost' => $rate ?? 0,
        ];
    }

    public static function templateInfo(string $type, Model $master): array
    {
        $data = self::masterAttributes($type, $master);
        $costType = $data['cost_type'];

        return $data + [
            'automatic_quantity' => self::automaticQuantity($costType, $data['unit_capacity']),
            'uses_duration' => in_array($costType, self::DURATION_TYPES, true),
            'duration_label' => $costType === 'per_day' ? 'hari' : 'malam',
            'quantity_label' => match ($costType) {
                'per_room' => 'Jumlah kamar',
                'per_vehicle' => 'Jumlah kendaraan',
                'per_person' => 'Seluruh peserta trip',
                'percentage' => 'Mengikuti dasar persentase',
                default => 'Jumlah unit layanan',
            },
        ];
    }

    public static function automaticQuantity(string $costType, ?int $capacity): bool
    {
        return in_array($costType, ['per_person', 'percentage'], true)
            || ($capacity > 0 && in_array($costType, self::UNIT_TYPES, true));
    }

    /**
     * Variable bila bergantung pada jumlah peserta; Fixed bila tidak.
     * Per orang untuk semua peserta dan unit hasil kapasitas = Variable.
     * Per orang untuk sebagian peserta (jumlah orang diisi) = Fixed.
     */
    public static function behaviorFor(string $costType, bool $hasUnits, ?int $peopleCount): string
    {
        if ($costType === 'per_person') {
            return $peopleCount !== null ? 'fixed' : 'variable';
        }
        if ($costType === 'percentage') {
            return 'variable';
        }
        if (in_array($costType, self::UNIT_TYPES, true)) {
            return $hasUnits ? 'variable' : 'fixed';
        }

        return 'fixed'; // per_trip
    }

    public static function defaultDuration(string $costType, Trip $trip): string
    {
        return match ($costType) {
            'per_room', 'per_night' => (string) $trip->nights,
            'per_day' => (string) $trip->duration_days,
            default => '1',
        };
    }
}
