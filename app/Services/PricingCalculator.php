<?php

namespace App\Services;

use InvalidArgumentException;

class PricingCalculator
{
    public const SCALE = 4;

    /** Cost Plus Margin: biaya / (1 - margin). Cost Plus Markup: biaya x (1 + markup). */
    public function calculateRecommendedPrice(string $cost, string $method, string $ratePercent, string $rounding = 'exact'): string
    {
        $cost = $this->num($cost);
        $rate = $this->num($ratePercent);

        $price = match ($method) {
            'cost_plus_margin' => $this->fromMargin($cost, $rate),
            'cost_plus_markup' => bcmul($cost, bcadd('1', bcdiv($rate, '100', 8), 8), self::SCALE),
            default => throw new InvalidArgumentException("Metode harga tidak dikenal: {$method}"),
        };

        return $this->roundPrice($price, $rounding);
    }

    private function fromMargin(string $cost, string $rate): string
    {
        if (bccomp($rate, '0', 8) < 0 || bccomp($rate, '100', 8) >= 0) {
            throw new InvalidArgumentException('Target margin harus 0 sampai di bawah 100 persen.');
        }

        $divisor = bcsub('1', bcdiv($rate, '100', 8), 8);

        return bcdiv($cost, $divisor, self::SCALE);
    }

    /** exact = dua desimal; nearest_N = pembulatan terdekat ke kelipatan N (setengah ke atas). */
    public function roundPrice(string $price, string $rule = 'exact'): string
    {
        $price = $this->num($price);

        if ($rule === 'exact') {
            return bcadd($price, '0.005', 2);
        }

        if (! preg_match('/^nearest_(\d+)$/', $rule, $m) || (int) $m[1] < 1) {
            throw new InvalidArgumentException("Aturan pembulatan tidak dikenal: {$rule}");
        }

        $step = $m[1];
        $units = bcdiv(bcadd($price, bcdiv($step, '2', self::SCALE), self::SCALE), $step, 0);

        return bcmul($units, $step, 2);
    }

    /** Margin (persen) = (harga - biaya) / harga x 100. Null bila harga nol. */
    public function calculateMargin(string $price, string $cost): ?string
    {
        $price = $this->num($price);
        if (bccomp($price, '0', self::SCALE) <= 0) {
            return null;
        }

        return bcmul(bcdiv(bcsub($price, $this->num($cost), self::SCALE), $price, 8), '100', self::SCALE);
    }

    public function calculateEstimatedProfit(string $price, string $cost): string
    {
        return bcsub($this->num($price), $this->num($cost), self::SCALE);
    }

    /** @return array{amount: string, percent: string} */
    public function calculateVendorSaving(string $standard, string $negotiated): array
    {
        $standard = $this->num($standard);
        $amount = bcsub($standard, $this->num($negotiated), self::SCALE);
        $percent = bccomp($standard, '0', self::SCALE) > 0
            ? bcmul(bcdiv($amount, $standard, 8), '100', self::SCALE)
            : '0';

        return ['amount' => $amount, 'percent' => $percent];
    }

    /** Customer Discount = Recommended Price - Final Price. */
    public function calculateCustomerDiscount(string $recommended, string $final): string
    {
        return bcsub($this->num($recommended), $this->num($final), self::SCALE);
    }

    /**
     * Harga per pax = total / pax. Null bila pax < 1.
     * Rounding default 'exact' (dua desimal, setengah ke atas).
     */
    public function calculatePricePerPax(string $total, int $pax, string $rounding = 'exact'): ?string
    {
        if ($pax < 1) {
            return null;
        }

        return $this->roundPrice(bcdiv($this->num($total), (string) $pax, self::SCALE), $rounding);
    }

    /** Profit per orang = profit / pax. Null bila pax < 1. */
    public function calculateProfitPerPax(string $profit, int $pax): ?string
    {
        if ($pax < 1) {
            return null;
        }

        return bcdiv($this->num($profit), (string) $pax, 2);
    }

    /**
     * Konversi harga per pax -> total.
     * Bila $perPax false, hanya normalisasi ke 2 desimal.
     */
    public function calculateTotalFromPerPax(string $valuePerPax, int $pax, bool $perPax = true): string
    {
        $v = $this->num($valuePerPax);

        return $perPax ? bcmul($v, (string) max($pax, 1), 2) : $this->toMoney($v);
    }

    /** Normalisasi nilai uang ke 2 desimal. */
    public function toMoney(mixed $value): string
    {
        return bcadd($this->num($value), '0', 2);
    }

    /** Menormalkan angka uang ke dua desimal */
    public function normalizeMoney(mixed $value): string
    {
        return $this->toMoney($value);
    }

    /** Nilai absolut uang (untuk diskon negatif = di atas rekomendasi). */
    public function absoluteMoney(string $value): string
    {
        $v = $this->num($value);

        return bccomp($v, '0', self::SCALE) < 0 ? bcmul($v, '-1', 2) : $this->toMoney($v);
    }

    /** Membulatkan ke dua desimal (setengah menjauhi nol), aman untuk nilai negatif. */
    public function roundMoney(mixed $value): string
    {
        $value = $this->num($value);
        $negative = bccomp($value, '0', self::SCALE) < 0;
        $abs = $negative ? bcmul($value, '-1', self::SCALE) : $value;
        $rounded = bcadd($abs, '0.005', 2);

        return ($negative && bccomp($rounded, '0', 2) !== 0) ? bcmul($rounded, '-1', 2) : $rounded;
    }

    /** Total harga = cost + profit (untuk pratinjau estimasi). */
    public function calculateTotalPrice(string $cost, string $profit): string
    {
        return bcadd($this->num($cost), $this->num($profit), 2);
    }

    public function isBelowMinimum(?string $margin, ?string $threshold): bool
    {
        if ($threshold === null || $threshold === '') {
            return false;
        }
        if ($margin === null) {
            return true;
        }

        return bccomp($margin, $this->num($threshold), self::SCALE) < 0;
    }

    private function num(mixed $value): string
    {
        if ($value === null || $value === '') {
            return '0';
        }
        if (is_string($value) && preg_match('/^[+-]?\d+(\.\d+)?$/', $value)) {
            return $value;
        }

        return sprintf('%.'.self::SCALE.'F', (float) $value);
    }
}