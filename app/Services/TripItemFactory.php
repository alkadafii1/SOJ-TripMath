<?php

namespace App\Services;

use App\Models\PackageVersionItem;
use App\Models\Trip;
use App\Support\Options;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;

class TripItemFactory
{
    private const CAPACITY_TYPES = ['per_room', 'per_vehicle', 'per_day', 'per_night'];

    public static function make(string $type, Model $m, Trip $trip): array
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

        $capacity = (in_array($costType, self::CAPACITY_TYPES, true) && $capacity) ? (int) $capacity : null;
        $isCostItem = $type === 'cost_item';

        return [
            'item_type' => $type,
            'item_id' => $m->id,
            'vendor_id' => $m->vendor_id ?? null,
            'category' => $category,
            'item_name' => PackageVersionItem::labelFor($type, $m),
            'cost_type' => $costType,
            'cost_behavior' => $isCostItem ? $m->cost_behavior : self::defaultBehavior($costType, $capacity !== null),
            'duration_qty' => self::defaultDuration($costType, $trip),
            'unit_capacity' => $capacity,
            'percentage' => $isCostItem ? $m->percentage : null,
            'base_type' => $isCostItem ? $m->base_type : null,
            'standard_cost' => $rate ?? 0,
        ];
    }

    /** Variable bila bergantung pada pax (per orang, persentase, atau kamar/kendaraan yang dihitung dari kapasitas). */
    public static function defaultBehavior(string $costType, bool $hasCapacity): string
    {
        return (in_array($costType, ['per_person', 'percentage'], true) || $hasCapacity) ? 'variable' : 'fixed';
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