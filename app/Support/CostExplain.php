<?php

namespace App\Support;

use App\Models\TripCostItem;

class CostExplain
{
    /** Kalimat perhitungan, misalnya "5 orang × Rp50.000". */
    public static function line(string $type, array $v, bool $withRate = true): string
    {
        $n = fn ($x) => rtrim(rtrim(number_format((float) $x, 4, '.', ''), '0'), '.');
        $rate = $withRate ? ' × '.Money::rp($v['rate'] ?? 0) : '';

        return match ($type) {
            'per_person' => $n($v['people'] ?? 0).' orang'.$rate,
            'per_room' => $n($v['units'] ?? 0).' kamar × '.$n($v['duration'] ?? 1).' malam'.$rate,
            'per_night' => $n($v['units'] ?? 0).' unit × '.$n($v['duration'] ?? 1).' malam'.$rate,
            'per_day' => $n($v['units'] ?? 0).' unit × '.$n($v['duration'] ?? 1).' hari'.$rate,
            'per_vehicle' => $n($v['units'] ?? 0).' kendaraan'.$rate,
            'per_trip' => $n($v['quantity'] ?? 1).' unit'.$rate,
            'percentage' => $n($v['percentage'] ?? 0).'% dari '.(($v['base_type'] ?? 'cost') === 'revenue' ? 'harga jual' : 'total biaya lain'),
            default => '-',
        };
    }

    public static function forItem(TripCostItem $i, ?array $line, int $pax, bool $withRate): string
    {
        return self::line($i->cost_type, [
            'rate' => $line['rate'] ?? $i->standard_cost,
            'units' => $line['units'] ?? $i->quantity,
            'duration' => $i->duration_qty ?? 1,
            'people' => $i->people_count ?? $pax,
            'quantity' => $i->quantity,
            'percentage' => $i->percentage,
            'base_type' => $i->base_type,
        ], $withRate);
    }
}