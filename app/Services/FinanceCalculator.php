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

    /**
     * Persentase bagian terhadap dasar hitung (4 desimal), misalnya diskon terhadap harga
     * rekomendasi atau panjang batang grafik terhadap nilai terbesar.
     * Dasar nol atau negatif menghasilkan '0.0000' karena perbandingan tidak terdefinisi.
     */
    public function calculateRatioPercent(string $amount, string $base): string
    {
        $base = $this->num($base);
        if (bccomp($base, '0', self::SCALE) <= 0) {
            return '0.0000';
        }

        return bcmul(bcdiv($this->num($amount), $base, 8), '100', 4);
    }

    /** Menjumlahkan persentase (4 desimal). */
    public function sumPercent(array $percents): string
    {
        $total = '0';
        foreach ($percents as $percent) {
            $total = bcadd($total, $this->num($percent), 4);
        }

        return $total;
    }

    /** True bila total persentase tepat 100. */
    public function isTotalHundred(array $percents): bool
    {
        return bccomp($this->sumPercent($percents), '100', 4) === 0;
    }

    /**
     * Marketing Allocation = persentase x dasar hitung.
     * Bila trip merugi, dasar actual_profit dianggap 0 sehingga tidak ada alokasi dari kerugian.
     */
    public function calculateMarketingAllocation(string $revenue, string $actualProfit, string $percent, string $base): string
    {
        $baseAmount = match ($base) {
            'revenue' => $this->num($revenue),
            'actual_profit' => bccomp($this->num($actualProfit), '0', self::SCALE) > 0 ? $this->num($actualProfit) : '0',
            default => throw new \InvalidArgumentException("Dasar marketing allocation tidak dikenal: {$base}"),
        };

        return $this->pricing->roundMoney(bcmul($baseAmount, bcdiv($this->num($percent), '100', 8), 8));
    }

    /** Distributable Profit = Actual Profit - Marketing Allocation. */
    public function calculateDistributable(string $actualProfit, string $allocation): string
    {
        return bcsub($this->num($actualProfit), $this->num($allocation), self::SCALE);
    }

    /**
     * Membagi Distributable Profit sesuai persentase (total wajib 100). Tiap bagian dibulatkan ke dua
     * desimal; sisa pembulatan masuk ke $remainderParty sehingga jumlah bagian persis sama dengan totalnya.
     *
     * @return array<string, array{percent: string, amount: string}>
     */
    public function calculateProfitDistribution(string $distributable, array $percents, string $remainderParty = 'owner'): array
    {
        if (! $this->isTotalHundred($percents)) {
            throw new \InvalidArgumentException('Total persentase bagi hasil harus 100, sekarang '.$this->sumPercent($percents).'.');
        }
        if (! array_key_exists($remainderParty, $percents)) {
            throw new \InvalidArgumentException("Pihak penerima sisa pembulatan tidak ditemukan: {$remainderParty}");
        }

        $total = $this->pricing->roundMoney($distributable);
        $amounts = [];
        $assigned = '0';

        foreach ($percents as $party => $percent) {
            if ($party === $remainderParty) {
                continue;
            }
            $amounts[$party] = $this->pricing->roundMoney(bcmul($total, bcdiv($this->num($percent), '100', 8), 8));
            $assigned = bcadd($assigned, $amounts[$party], self::SCALE);
        }
        $amounts[$remainderParty] = $this->pricing->roundMoney(bcsub($total, $assigned, self::SCALE));

        $result = [];
        foreach ($percents as $party => $percent) {
            $result[$party] = ['percent' => $this->num($percent), 'amount' => $amounts[$party]];
        }

        return $result;
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