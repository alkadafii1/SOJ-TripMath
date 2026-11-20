<?php

namespace App\Services;

use App\Models\Trip;
use App\Models\TripSnapshot;
use App\Support\Options;
use App\Support\ReportPeriod;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

/**
 * Data untuk dashboard dan laporan. Semua angka berasal dari snapshot trip yang sudah direkonsiliasi
 * (angka historis yang dibekukan), dan semua hitungan uang memakai mesin hitung.
 */
class ReportService
{
    public const REPORTS = [
        'trip-profit' => 'Laporan Profit Trip',
        'vendor-saving' => 'Laporan Vendor Saving',
        'customer-discount' => 'Laporan Customer Discount',
        'profit-sharing' => 'Laporan Bagi Hasil',
        'cost-variance' => 'Laporan Selisih Biaya',
        'trend' => 'Tren Revenue dan Margin',
    ];

    public function __construct(private FinanceCalculator $finance, private PricingCalculator $pricing)
    {
    }

    /** Snapshot trip yang selesai dalam periode, urut tanggal selesai. */
    public function snapshots(ReportPeriod $period): Collection
    {
        return TripSnapshot::query()
            ->join('trips', 'trips.id', '=', 'trip_snapshots.trip_id')
            ->whereNull('trips.deleted_at')
            ->whereDate('trips.end_date', '>=', $period->from->toDateString())
            ->whereDate('trips.end_date', '<=', $period->to->toDateString())
            ->orderBy('trips.end_date')
            ->orderBy('trips.id')
            ->select('trip_snapshots.*')
            ->with(['trip.pricing'])
            ->get();
    }

    /** Angka ringkasan dashboard. Average Margin adalah margin gabungan: total profit dibagi total revenue. */
    public function summary(ReportPeriod $period): array
    {
        $rows = $this->snapshots($period);
        $withSplit = $rows->filter(fn ($s) => $s->marketing_allocation !== null);

        $revenue = $this->sumOf($rows, 'revenue');
        $actualCost = $this->sumOf($rows, 'actual_cost');

        return [
            'trips' => $rows->count(),
            'revenue' => $revenue,
            'standard_cost' => $this->sumOf($rows, 'standard_cost'),
            'negotiated_cost' => $this->sumOf($rows, 'negotiated_cost'),
            'actual_cost' => $actualCost,
            'vendor_saving' => $this->sumOf($rows, 'vendor_saving'),
            'customer_discount' => $this->sumOf($rows, 'customer_discount'),
            'actual_profit' => $this->sumOf($rows, 'actual_profit'),
            'average_margin' => $this->pricing->calculateMargin($revenue, $actualCost),
            'marketing_allocation' => $this->sumOf($withSplit, 'marketing_allocation'),
            'distributable_profit' => $this->sumOf($withSplit, 'distributable_profit'),
            'owner_share' => $this->sumOf($withSplit, 'owner_share'),
            'partner_share' => $this->sumOf($withSplit, 'partner_share'),
            'trips_without_sharing' => $rows->count() - $withSplit->count(),
        ];
    }

    /** Jumlah trip per status untuk trip yang selesai dalam periode (termasuk yang belum direkonsiliasi). */
    public function statusCounts(ReportPeriod $period): array
    {
        $counts = Trip::query()
            ->selectRaw('status, count(*) as total')
            ->whereDate('end_date', '>=', $period->from->toDateString())
            ->whereDate('end_date', '<=', $period->to->toDateString())
            ->groupBy('status')
            ->pluck('total', 'status')
            ->all();

        $ordered = [];
        foreach (array_keys(Trip::STATUS) as $status) {
            if (($counts[$status] ?? 0) > 0) {
                $ordered[$status] = (int) $counts[$status];
            }
        }

        return $ordered;
    }

    /**
     * @return array{key: string, title: string, columns: array, rows: array, totals: ?array, notes: array}
     */
    public function report(string $key, ReportPeriod $period): array
    {
        $snapshots = $this->snapshots($period);

        $built = match ($key) {
            'trip-profit' => $this->tripProfit($snapshots),
            'vendor-saving' => $this->vendorSaving($snapshots),
            'customer-discount' => $this->customerDiscount($snapshots),
            'profit-sharing' => $this->profitSharing($snapshots),
            'cost-variance' => $this->costVariance($snapshots),
            'trend' => $this->trend($snapshots),
            default => throw new \InvalidArgumentException("Laporan tidak dikenal: {$key}"),
        };

        return ['key' => $key, 'title' => self::REPORTS[$key]] + $built + ['notes' => $built['notes'] ?? []];
    }

    // ---------------------------------------------------------------- laporan

    private function tripProfit(Collection $snaps): array
    {
        $columns = [
            $this->col('trip_code', 'Kode'), $this->col('trip_name', 'Trip'), $this->col('customer', 'Customer'),
            $this->col('type', 'Tipe'), $this->col('end_date', 'Selesai', 'date'), $this->col('pax', 'Pax', 'int'),
            $this->col('revenue', 'Revenue', 'money'), $this->col('actual_cost', 'Actual Cost', 'money'),
            $this->col('actual_profit', 'Actual Profit', 'signed'), $this->col('margin', 'Margin', 'percent'),
            $this->col('profit_per_pax', 'Profit/orang', 'signed'),
        ];

        $rows = $snaps->map(function (TripSnapshot $s) {
            $pax = $s->trip->effectivePax();

            return $this->base($s) + [
                'pax' => $pax,
                'revenue' => (string) $s->revenue,
                'actual_cost' => (string) $s->actual_cost,
                'actual_profit' => (string) $s->actual_profit,
                'margin' => $this->pricing->calculateMargin((string) $s->revenue, (string) $s->actual_cost),
                'profit_per_pax' => $this->pricing->calculateProfitPerPax((string) $s->actual_profit, $pax),
            ];
        })->all();

        $revenue = $this->sumRows($rows, 'revenue');
        $cost = $this->sumRows($rows, 'actual_cost');
        $profit = $this->sumRows($rows, 'actual_profit');
        $pax = (int) array_sum(array_column($rows, 'pax'));

        return [
            'columns' => $columns,
            'rows' => $rows,
            'totals' => [
                'pax' => $pax, 'revenue' => $revenue, 'actual_cost' => $cost, 'actual_profit' => $profit,
                'margin' => $this->pricing->calculateMargin($revenue, $cost),
                'profit_per_pax' => $this->pricing->calculateProfitPerPax($profit, $pax),
            ],
        ];
    }

    private function vendorSaving(Collection $snaps): array
    {
        $columns = [
            $this->col('trip_code', 'Kode'), $this->col('trip_name', 'Trip'), $this->col('end_date', 'Selesai', 'date'),
            $this->col('standard_cost', 'Standard Cost', 'money'), $this->col('negotiated_cost', 'Negotiated Cost', 'money'),
            $this->col('vendor_saving', 'Vendor Saving', 'signed'), $this->col('saving_percent', 'Hemat', 'percent'),
        ];

        $rows = $snaps->map(fn (TripSnapshot $s) => $this->base($s) + [
            'standard_cost' => (string) $s->standard_cost,
            'negotiated_cost' => (string) $s->negotiated_cost,
            'vendor_saving' => (string) $s->vendor_saving,
            'saving_percent' => $this->pricing->calculateVendorSaving((string) $s->standard_cost, (string) $s->negotiated_cost)['percent'],
        ])->all();

        $standard = $this->sumRows($rows, 'standard_cost');
        $negotiated = $this->sumRows($rows, 'negotiated_cost');
        $total = $this->pricing->calculateVendorSaving($standard, $negotiated);

        return [
            'columns' => $columns,
            'rows' => $rows,
            'totals' => [
                'standard_cost' => $standard, 'negotiated_cost' => $negotiated,
                'vendor_saving' => $total['amount'], 'saving_percent' => $rows === [] ? null : $total['percent'],
            ],
        ];
    }

    private function customerDiscount(Collection $snaps): array
    {
        $columns = [
            $this->col('trip_code', 'Kode'), $this->col('trip_name', 'Trip'), $this->col('end_date', 'Selesai', 'date'),
            $this->col('recommended_price', 'Harga rekomendasi', 'money'), $this->col('final_price', 'Harga final', 'money'),
            $this->col('customer_discount', 'Diskon', 'signed'), $this->col('discount_percent', 'Diskon %', 'percent'),
        ];

        $rows = $snaps->map(function (TripSnapshot $s) {
            $recommended = $s->trip->pricing?->recommended_price;
            $discount = $s->customer_discount;

            return $this->base($s) + [
                'recommended_price' => $recommended === null ? null : (string) $recommended,
                'final_price' => (string) $s->revenue,
                'customer_discount' => $discount === null ? null : (string) $discount,
                'discount_percent' => ($recommended !== null && $discount !== null)
                    ? $this->finance->calculateRatioPercent((string) $discount, (string) $recommended)
                    : null,
            ];
        })->all();

        // Persentase total hanya dari trip yang punya harga rekomendasi dan diskon
        $both = array_filter($rows, fn ($r) => $r['recommended_price'] !== null && $r['customer_discount'] !== null);
        $recommendedSum = $this->sumRows($both, 'recommended_price');
        $discountSum = $this->sumRows($both, 'customer_discount');

        return [
            'columns' => $columns,
            'rows' => $rows,
            'totals' => [
                'recommended_price' => $this->sumRows($rows, 'recommended_price'),
                'final_price' => $this->sumRows($rows, 'final_price'),
                'customer_discount' => $this->sumRows($rows, 'customer_discount'),
                'discount_percent' => $this->finance->calculateRatioPercent($discountSum, $recommendedSum),
            ],
            'notes' => ['Diskon positif berarti harga final di bawah harga rekomendasi; negatif berarti di atas harga rekomendasi.'],
        ];
    }

    private function profitSharing(Collection $snaps): array
    {
        $columns = [
            $this->col('trip_code', 'Kode'), $this->col('trip_name', 'Trip'), $this->col('end_date', 'Selesai', 'date'),
            $this->col('actual_profit', 'Actual Profit', 'signed'), $this->col('marketing_allocation', 'Marketing', 'signed'),
            $this->col('distributable_profit', 'Distributable', 'signed'), $this->col('owner_share', 'Owner', 'signed'),
            $this->col('partner_share', 'Partner', 'signed'),
        ];

        $rows = $snaps->map(fn (TripSnapshot $s) => $this->base($s) + [
            'actual_profit' => (string) $s->actual_profit,
            'marketing_allocation' => $s->marketing_allocation === null ? null : (string) $s->marketing_allocation,
            'distributable_profit' => $s->distributable_profit === null ? null : (string) $s->distributable_profit,
            'owner_share' => $s->owner_share === null ? null : (string) $s->owner_share,
            'partner_share' => $s->partner_share === null ? null : (string) $s->partner_share,
        ])->all();

        $without = count(array_filter($rows, fn ($r) => $r['owner_share'] === null));

        return [
            'columns' => $columns,
            'rows' => $rows,
            'totals' => [
                'actual_profit' => $this->sumRows($rows, 'actual_profit'),
                'marketing_allocation' => $this->sumRows($rows, 'marketing_allocation'),
                'distributable_profit' => $this->sumRows($rows, 'distributable_profit'),
                'owner_share' => $this->sumRows($rows, 'owner_share'),
                'partner_share' => $this->sumRows($rows, 'partner_share'),
            ],
            'notes' => $without > 0
                ? ["{$without} trip direkonsiliasi sebelum aturan bagi hasil tersedia, jadi bagiannya kosong dan tidak ikut dijumlah."]
                : [],
        ];
    }

    private function costVariance(Collection $snaps): array
    {
        $columns = [
            $this->col('trip_code', 'Kode'), $this->col('trip_name', 'Trip'), $this->col('end_date', 'Selesai', 'date'),
            $this->col('standard_cost', 'Standard', 'money'), $this->col('negotiated_cost', 'Negotiated', 'money'),
            $this->col('actual_cost', 'Actual', 'money'),
            $this->col('variance_negotiated', 'Selisih vs negosiasi', 'signed'), $this->col('variance_negotiated_percent', '%', 'percent'),
            $this->col('variance_standard', 'Selisih vs standar', 'signed'), $this->col('variance_standard_percent', '%', 'percent'),
        ];

        $rows = $snaps->map(function (TripSnapshot $s) {
            $vn = $this->finance->calculateVariance((string) $s->negotiated_cost, (string) $s->actual_cost);
            $vs = $this->finance->calculateVariance((string) $s->standard_cost, (string) $s->actual_cost);

            return $this->base($s) + [
                'standard_cost' => (string) $s->standard_cost,
                'negotiated_cost' => (string) $s->negotiated_cost,
                'actual_cost' => (string) $s->actual_cost,
                'variance_negotiated' => $vn['amount'], 'variance_negotiated_percent' => $vn['percent'],
                'variance_standard' => $vs['amount'], 'variance_standard_percent' => $vs['percent'],
            ];
        })->all();

        $standard = $this->sumRows($rows, 'standard_cost');
        $negotiated = $this->sumRows($rows, 'negotiated_cost');
        $actual = $this->sumRows($rows, 'actual_cost');
        $vn = $this->finance->calculateVariance($negotiated, $actual);
        $vs = $this->finance->calculateVariance($standard, $actual);

        return [
            'columns' => $columns,
            'rows' => $rows,
            'totals' => [
                'standard_cost' => $standard, 'negotiated_cost' => $negotiated, 'actual_cost' => $actual,
                'variance_negotiated' => $vn['amount'], 'variance_negotiated_percent' => $vn['percent'],
                'variance_standard' => $vs['amount'], 'variance_standard_percent' => $vs['percent'],
            ],
            'notes' => ['Selisih positif berarti biaya sebenarnya lebih mahal dari rencana; negatif berarti lebih hemat.'],
        ];
    }

    private function trend(Collection $snaps): array
    {
        $columns = [
            $this->col('period', 'Bulan'), $this->col('trips', 'Trip', 'int'),
            $this->col('revenue', 'Revenue', 'money'), $this->col('actual_cost', 'Actual Cost', 'money'),
            $this->col('actual_profit', 'Actual Profit', 'signed'), $this->col('margin', 'Margin', 'percent'),
        ];

        $groups = $snaps->groupBy(fn (TripSnapshot $s) => $s->trip->end_date->format('Y-m'))->sortKeys();

        $rows = [];
        foreach ($groups as $month => $group) {
            $revenue = $this->sumOf($group, 'revenue');
            $cost = $this->sumOf($group, 'actual_cost');

            $rows[] = [
                '_trip_id' => null,
                'period' => $group->first()->trip->end_date->locale('id')->translatedFormat('F Y'),
                'trips' => $group->count(),
                'revenue' => $revenue,
                'actual_cost' => $cost,
                'actual_profit' => $this->sumOf($group, 'actual_profit'),
                'margin' => $this->pricing->calculateMargin($revenue, $cost),
            ];
        }

        // Lebar batang (persen terhadap revenue bulan tertinggi), hanya untuk tampilan
        $max = '0';
        foreach ($rows as $row) {
            $max = bccomp($row['revenue'], $max, 2) > 0 ? $row['revenue'] : $max;
        }
        foreach ($rows as $i => $row) {
            $rows[$i]['_bar'] = $this->finance->calculateRatioPercent($row['revenue'], $max);
        }

        $revenue = $this->sumRows($rows, 'revenue');
        $cost = $this->sumRows($rows, 'actual_cost');

        return [
            'columns' => $columns,
            'rows' => $rows,
            'totals' => [
                'trips' => (int) array_sum(array_column($rows, 'trips')),
                'revenue' => $revenue, 'actual_cost' => $cost,
                'actual_profit' => $this->sumRows($rows, 'actual_profit'),
                'margin' => $this->pricing->calculateMargin($revenue, $cost),
            ],
        ];
    }

    // ---------------------------------------------------------------- ekspor

    /**
     * Baris CSV: judul kolom, data, lalu baris total. Sel teks yang diawali karakter rumus
     * (=, +, -, @) diberi awalan petik agar tidak dieksekusi Excel (CSV injection).
     *
     * @return array<int, array<int, string>>
     */
    public function csvRows(array $report): array
    {
        $columns = $report['columns'];
        $lines = [array_map(fn ($c) => $c['label'], $columns)];

        foreach ($report['rows'] as $row) {
            $lines[] = array_map(fn ($c) => $this->csvCell($c['type'], $row[$c['key']] ?? null), $columns);
        }

        if ($report['totals'] !== null && $report['rows'] !== []) {
            $total = [];
            foreach ($columns as $i => $c) {
                $total[] = $i === 0 ? 'Total' : $this->csvCell($c['type'], $report['totals'][$c['key']] ?? null);
            }
            $lines[] = $total;
        }

        return $lines;
    }

    private function csvCell(string $type, mixed $value): string
    {
        if ($value === null || $value === '') {
            return '';
        }

        return match ($type) {
            'money', 'signed', 'percent' => $this->pricing->roundMoney($value),
            'int' => (string) (int) $value,
            'date' => $value instanceof CarbonInterface ? $value->format('Y-m-d') : (string) $value,
            default => $this->safeText((string) $value),
        };
    }

    private function safeText(string $value): string
    {
        return preg_match('/^[=+\-@\t\r]/', $value) ? "'".$value : $value;
    }

    // ---------------------------------------------------------------- pembantu

    private function col(string $key, string $label, string $type = 'text'): array
    {
        return ['key' => $key, 'label' => $label, 'type' => $type];
    }

    /** Kolom identitas trip yang dipakai hampir semua laporan. */
    private function base(TripSnapshot $s): array
    {
        $trip = $s->trip;

        return [
            '_trip_id' => $trip->id,
            'trip_code' => $trip->trip_code,
            'trip_name' => $trip->trip_name,
            'customer' => $trip->customer_name,
            'type' => Options::TRIP_TYPES[$trip->trip_type] ?? $trip->trip_type,
            'end_date' => $trip->end_date,
        ];
    }

    private function sumOf(Collection $rows, string $column): string
    {
        return $this->finance->sum($rows->pluck($column)->filter(fn ($v) => $v !== null)->map(fn ($v) => (string) $v)->all());
    }

    private function sumRows(array $rows, string $key): string
    {
        $values = [];
        foreach ($rows as $row) {
            if (($row[$key] ?? null) !== null) {
                $values[] = (string) $row[$key];
            }
        }

        return $this->finance->sum($values);
    }
}
