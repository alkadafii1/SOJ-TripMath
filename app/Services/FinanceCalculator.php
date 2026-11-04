<?php

namespace App\Services;

/**
 * Rumus keuangan setelah harga disepakati: pembayaran, selisih biaya, dan profit aktual.
 * Bersama TripCalculator dan PricingCalculator, ini satu-satunya tempat hitungan uang ditulis.
 */
class FinanceCalculator
{
    public const SCALE = 2;

    public function __construct(private PricingCalculator $pricing)
    {
    }

    /** Menjumlahkan daftar nominal uang. */
    public function sum(array $amounts): string
    {
        $total = '0';
        foreach ($amounts as $amount) {
            $total = bcadd($total, $this->num($amount), self::SCALE);
        }

        return $total;
    }

    /** Sisa tagihan = harga final - total dibayar. */
    public function calculateOutstanding(string $final, string $paid): string
    {
        return bcsub($this->num($final), $this->num($paid), self::SCALE);
    }

    /** @return string unpaid | partial | paid | overpaid */
    public function paymentState(string $final, string $paid): string
    {
        $paid = $this->num($paid);
        $final = $this->num($final);

        if (bccomp($paid, '0', self::SCALE) <= 0) {
            return 'unpaid';
        }

        $cmp = bccomp($paid, $final, self::SCALE);

        return match (true) {
            $cmp < 0 => 'partial',
            $cmp === 0 => 'paid',
            default => 'overpaid',
        };
    }

    /**
     * Selisih = biaya sebenarnya - pembanding. Positif berarti lebih mahal dari pembanding.
     * Persen dihitung terhadap pembanding; null bila pembanding nol.
     *
     * @return array{amount: string, percent: ?string}
     */
    public function calculateVariance(string $baseline, string $actual): array
    {
        $baseline = $this->num($baseline);
        $amount = bcsub($this->num($actual), $baseline, self::SCALE);

        $percent = bccomp($baseline, '0', self::SCALE) > 0
            ? bcmul(bcdiv($amount, $baseline, 8), '100', 4)
            : null;

        return ['amount' => $amount, 'percent' => $percent];
    }

    /**
     * Actual Profit = Revenue - Actual Cost.
     *
     * @return array{profit: string, margin: ?string, per_pax: ?string}
     */
    public function calculateProfit(string $revenue, string $actualCost, int $pax): array
    {
        $profit = $this->pricing->normalizeMoney($this->pricing->calculateEstimatedProfit($revenue, $actualCost));

        return [
            'profit' => $profit,
            'margin' => $this->pricing->calculateMargin($revenue, $actualCost),
            'per_pax' => $this->pricing->calculateProfitPerPax($profit, $pax),
        ];
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