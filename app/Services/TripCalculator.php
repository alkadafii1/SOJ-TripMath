<?php

namespace App\Services;

use InvalidArgumentException;

class TripCalculator
{
    public const SCALE = 4;

    /** Tipe biaya yang jumlah unitnya bisa dihitung dari kapasitas (kamar, kendaraan). */
    private const UNIT_TYPES = ['per_room', 'per_vehicle', 'per_day', 'per_night'];

    /** CEILING(pax / kapasitas). Contoh: 4 pax, kamar isi 2 = 2 kamar. */
    public function calculateRoomRequirement(int $pax, int $capacity): int
    {
        if ($pax < 1 || $capacity < 1) {
            return 0;
        }

        return intdiv($pax + $capacity - 1, $capacity);
    }

    /**
     * @param array<int|string, array> $items  daftar item (kunci bebas, mis. id), bentuk lihat TripCostItem::toCalcArray()
     * @param string $costField  standard_cost | negotiated_cost | actual_cost
     * @param string|null $revenue  dasar untuk biaya persentase bertipe revenue
     */
    public function calculateTripCost(int $pax, array $items, string $costField = 'standard_cost', ?string $revenue = null): array
    {
        $lines = [];
        $base = '0';

        // Tahap 1: semua biaya selain persentase
        foreach ($items as $key => $item) {
            if (($item['cost_type'] ?? null) === 'percentage') {
                continue;
            }
            $lines[$key] = $this->calculateLine($pax, $item, $costField);
            $base = bcadd($base, $lines[$key]['amount'], self::SCALE);
        }

        // Tahap 2: biaya persentase, dihitung dari dasar yang sudah jadi
        foreach ($items as $key => $item) {
            if (($item['cost_type'] ?? null) !== 'percentage') {
                continue;
            }
            $baseAmount = ($item['base_type'] ?? 'cost') === 'revenue' ? ($revenue ?? '0') : $base;
            $amount = bcdiv(
                bcmul($this->num($baseAmount), $this->num($item['percentage'] ?? 0), self::SCALE),
                '100',
                self::SCALE
            );
            $lines[$key] = ['calculated_quantity' => null, 'units' => null, 'rate' => null, 'amount' => $amount];
        }

        $total = '0';
        $fixed = '0';
        $variable = '0';
        $byCategory = [];

        foreach ($items as $key => $item) {
            $amount = $lines[$key]['amount'];
            $total = bcadd($total, $amount, self::SCALE);

            if (($item['cost_behavior'] ?? 'variable') === 'fixed') {
                $fixed = bcadd($fixed, $amount, self::SCALE);
            } else {
                $variable = bcadd($variable, $amount, self::SCALE);
            }

            $category = $item['category'] ?? 'Lainnya';
            $byCategory[$category] = bcadd($byCategory[$category] ?? '0', $amount, self::SCALE);
        }

        return [
            'lines' => $lines,
            'total' => $total,
            'fixed_total' => $fixed,
            'variable_total' => $variable,
            'by_category' => $byCategory,
            'cost_per_person' => $pax > 0 ? bcdiv($total, (string) $pax, self::SCALE) : '0',
        ];
    }

    private function calculateLine(int $pax, array $item, string $costField): array
    {
        $type = $item['cost_type'] ?? null;

        $rate = $item[$costField] ?? null;
        if ($rate === null && $costField === 'negotiated_cost') {
            $rate = $item['standard_cost'] ?? 0; // belum dinegosiasikan = sama dengan standar
        }
        $rate = $this->num($rate ?? 0);

        $quantity = $this->num($item['quantity'] ?? 1);
        $duration = $this->num($item['duration_qty'] ?? 1);
        $capacity = (int) ($item['unit_capacity'] ?? 0);

        $calculated = null;
        if ($capacity > 0 && in_array($type, self::UNIT_TYPES, true)) {
            $calculated = $this->calculateRoomRequirement($pax, $capacity);
        }

        $override = $item['override_quantity'] ?? null;
        $units = ($override !== null && $override !== '')
            ? $this->num($override)
            : ($calculated !== null ? (string) $calculated : $quantity);

        $people = ($item['people_count'] ?? null) !== null && $item['people_count'] !== ''
        ? (string) (int) $item['people_count']
        : (string) $pax;

        $amount = match ($type) {
            'per_person' => $this->mul($rate, $people),
            'per_room', 'per_night', 'per_day' => $this->mul($rate, $units, $duration),
            'per_vehicle' => $this->mul($rate, $units),
            'per_trip' => $this->mul($rate, $quantity),
            default => throw new InvalidArgumentException("Tipe biaya tidak dikenal: {$type}"),
        };

        return [
            'calculated_quantity' => $calculated,
            'units' => in_array($type, self::UNIT_TYPES, true) ? $units : null,
            'rate' => $rate,
            'amount' => $amount,
        ];
    }

    private function num(mixed $value): string
    {
        if ($value === null || $value === '') {
            return '0';
        }
        if (is_string($value) && is_numeric($value)) {
            return $value;
        }

        return sprintf('%.'.self::SCALE.'F', (float) $value);
    }

    private function mul(string ...$numbers): string
    {
        $result = '1';
        foreach ($numbers as $n) {
            $result = bcmul($result, $n, self::SCALE);
        }

        return $result;
    }
}