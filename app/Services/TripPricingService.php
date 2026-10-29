<?php

namespace App\Services;

use App\Models\PricingRule;
use App\Models\Trip;
use App\Models\TripPricing;
use App\Support\Money;

class TripPricingService
{
    public function __construct(private TripCostService $costs, private PricingCalculator $calc)
    {
    }

    /** Aturan aktif hari ini. Yang punya tanggal efektif terbaru didahulukan. */
    public function activeRule(): ?PricingRule
    {
        return PricingRule::effectiveOn(now()->toDateString())
            ->orderByDesc('effective_from')
            ->orderByDesc('id')
            ->first();
    }

    /** Standard Cost, Negotiated Cost, dan Vendor Saving. */
    public function costTotals(Trip $trip): array
    {
        $standard = $this->costs->summary($trip, 'standard_cost');
        $negotiated = $this->costs->summary($trip, 'negotiated_cost');
        $saving = $this->calc->calculateVendorSaving($standard['total'], $negotiated['total']);

        return [
            'standard' => $standard,
            'negotiated' => $negotiated,
            'saving' => $saving['amount'],
            'saving_percent' => $saving['percent'],
        ];
    }

    /**
     * Harga rekomendasi dari Negotiated Cost.
     * Biaya persentase-dari-revenue bernilai 0 di sini karena revenue belum ada.
     */
    public function recommend(Trip $trip, string $method, string $rate, string $rounding, string $mode = 'dynamic'): array
    {
        $cost = $this->costs->summary($trip, 'negotiated_cost')['total'];
        $pax = $trip->effectivePax();

        $raw = $this->calc->calculateRecommendedPrice($cost, $method, $rate, 'exact');
        $engineTotal = $this->calc->roundPrice($raw, $rounding);

        $perPax = $this->calc->calculatePricePerPax($raw, $pax, $rounding);
        $recommended = ($mode === 'per_pax' && $perPax !== null)
            ? $this->calc->calculateTotalFromPerPax($perPax, $pax)
            : $engineTotal;

        return [
            'cost' => $cost,
            'engine_total' => $engineTotal,
            'per_pax' => $perPax,
            'recommended' => $recommended,
        ];
    }

    /** Evaluasi satu harga total: margin, estimasi profit, dan apakah di bawah batas minimum. */
    public function evaluate(Trip $trip, string $price, ?string $threshold, ?string $recommended = null): array
    {
        // Biaya persentase-dari-revenue memakai harga ini sebagai dasar
        $cost = $this->costs->summary($trip, 'negotiated_cost', $price)['total'];
        $margin = $this->calc->calculateMargin($price, $cost);
        $profit = $this->calc->calculateEstimatedProfit($price, $cost);
        $pax = $trip->effectivePax();

        return [
            'cost' => $cost,
            'margin' => $margin,
            'profit' => $profit,
            'profit_per_pax' => $this->calc->calculateProfitPerPax($profit, $pax),
            'below_min' => $this->calc->isBelowMinimum($margin, $threshold),
            'discount' => $recommended !== null ? $this->calc->calculateCustomerDiscount($recommended, $price) : null,
        ];
    }

    /** Alasan trip belum boleh dikonfirmasi, atau null bila boleh. */
    public function confirmationProblem(Trip $trip, TripPricing $pricing): ?string
    {
        if ($pricing->final_price === null || bccomp((string) $pricing->final_price, '0', 2) <= 0) {
            return 'Harga final belum disimpan.';
        }

        $threshold = $pricing->min_margin_threshold === null ? null : (string) $pricing->min_margin_threshold;
        $eval = $this->evaluate($trip, (string) $pricing->final_price, $threshold);

        if (! $eval['below_min']) {
            return null;
        }

        $detail = 'Margin '.Money::percent($eval['margin'] ?? 0).' di bawah batas minimum '.Money::percent($threshold).'.';

        if ($pricing->block_below_min) {
            return $detail.' Aturan harga memblokir konfirmasi; naikkan harga final.';
        }
        if ($pricing->require_approval) {
            return $pricing->approval_status === 'approved' ? null : $detail.' Butuh persetujuan Owner.';
        }

        return trim((string) $pricing->below_min_reason) === ''
            ? $detail.' Isi alasan harga di bawah batas dan simpan.'
            : null;
    }
}