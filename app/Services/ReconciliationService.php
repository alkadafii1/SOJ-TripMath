<?php

namespace App\Services;

use App\Models\Trip;
use App\Models\TripCostItem;
use App\Models\TripSnapshot;
use App\Support\Options;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use App\Models\ProfitDistribution;

class ReconciliationService
{
    public function __construct(
        private TripCostService $costs,
        private PaymentService $payments,
        private FinanceCalculator $finance,
        private TripCalculator $calc,
        private PricingCalculator $pricing,
        private ProfitRuleService $rules,
    ) {
    }

    /** Komponen biaya trip yang masih berlaku (termasuk biaya tambahan). */
    public function items(Trip $trip): Collection
    {
        return $trip->costItems()->active()->orderBy('is_additional')->orderBy('category')->orderBy('id')->get();
    }

    /** Seluruh angka yang dipakai halaman rekonsiliasi dan snapshot. */
    public function figures(Trip $trip): array
    {
        $items = $this->items($trip);

        $standard = $this->costs->summary($trip, 'standard_cost');
        $negotiated = $this->costs->summary($trip, 'negotiated_cost');

        $actual = $this->calc->calculateActualCost(
            $items->mapWithKeys(fn (TripCostItem $i) => [$i->id => [
                'category' => $i->category,
                'actual_cost' => $i->actual_cost,
                // Biaya tambahan tidak wajib; komponen rencana wajib kecuali ditandai opsional
                'is_required' => $i->is_required && ! $i->is_additional,
            ]])->all()
        );

        $lines = [];
        foreach ($items as $item) {
            $planned = $item->is_additional ? '0' : ($negotiated['lines'][$item->id]['amount'] ?? '0');
            $lines[$item->id] = [
                'planned' => $planned,
                'actual' => $item->actual_cost === null ? null : (string) $item->actual_cost,
                'variance' => $item->actual_cost === null ? null : $this->finance->calculateVariance($planned, (string) $item->actual_cost),
            ];
        }

        $pay = $this->payments->summary($trip);
        $revenue = $pay['final'];
        $pricing = $trip->pricing()->first();
        $saving = $this->pricing->calculateVendorSaving($standard['total'], $negotiated['total']);

        return [
            'lines' => $lines,
            'standard' => $standard['total'],
            'negotiated' => $negotiated['total'],
            'actual' => $actual['total'],
            'actual_by_category' => $actual['by_category'],
            'missing_ids' => $actual['missing'],
            'missing_names' => $items->whereIn('id', $actual['missing'])->pluck('item_name')->all(),
            'vendor_saving' => $saving['amount'],
            'variance_negotiated' => $this->finance->calculateVariance($negotiated['total'], $actual['total']),
            'variance_standard' => $this->finance->calculateVariance($standard['total'], $actual['total']),
            'revenue' => $revenue,
            'recommended_price' => $pricing?->recommended_price === null ? null : (string) $pricing->recommended_price,
            'customer_discount' => $pricing?->customer_discount === null ? null : (string) $pricing->customer_discount,
            'profit' => $revenue !== null ? $this->finance->calculateProfit($revenue, $actual['total'], $trip->effectivePax()) : null,
            'paid' => $pay['paid'],
            'outstanding' => $pay['outstanding'],
            'payment_count' => $pay['payments']->whereNull('voided_at')->count(),
            'rule_problem' => $this->rules->problem(),
            'split' => $revenue !== null
                ? $this->splitProfit($revenue, $this->finance->calculateProfit($revenue, $actual['total'], $trip->effectivePax())['profit'])
                : null,
        ];
    }

    /** Pembagian laba menurut aturan yang berlaku hari ini. Null bila belum ada aturan yang valid. */
    public function splitProfit(string $revenue, string $actualProfit): ?array
    {
        $rules = $this->rules->resolve();
        if ($rules === null) {
            return null;
        }

        $marketing = $this->finance->calculateMarketingAllocation(
            $revenue, $actualProfit, $rules['marketing']['percent'], $rules['marketing']['base']
        );
        $distributable = $this->finance->calculateDistributable($actualProfit, $marketing);
        $shares = $this->finance->calculateProfitDistribution($distributable, [
            'owner' => $rules['shares']['owner']['percent'],
            'partner' => $rules['shares']['partner']['percent'],
        ]);

        return [
            'rule_set' => $rules['rule_set'],
            'effective_from' => $rules['effective_from'],
            'marketing_percent' => $rules['marketing']['percent'],
            'marketing_base' => $rules['marketing']['base'],
            'marketing_allocation' => $marketing,
            'distributable' => $this->pricing->roundMoney($distributable),
            'shares' => $shares,
            'rules' => $rules,
        ];
    }

    /**
     * Checklist rekonsiliasi (spesifikasi bagian 25).
     * Pernyataan "biaya tambahan sudah diperiksa" diisi pengguna saat rekonsiliasi.
     *
     * @return array<int, array{key: string, label: string, ok: bool, detail: string}>
     */
    public function checklist(Trip $trip, array $figures): array
    {
        $completedOrAfter = in_array($trip->status, ['completed', 'reconciled'], true);

        return [
            [
                'key' => 'status',
                'label' => 'Trip sudah berstatus Completed',
                'ok' => $completedOrAfter,
                'detail' => $completedOrAfter ? '' : 'Status saat ini: '.(Trip::STATUS[$trip->status] ?? $trip->status).'.',
            ],
            [
                'key' => 'final_price',
                'label' => 'Harga final sudah ada',
                'ok' => $figures['revenue'] !== null,
                'detail' => $figures['revenue'] === null ? 'Harga final belum disepakati.' : '',
            ],
            [
                'key' => 'payment',
                'label' => 'Pembayaran sudah dicatat',
                'ok' => $figures['payment_count'] > 0,
                'detail' => $figures['payment_count'] > 0 ? '' : 'Belum ada pembayaran yang tercatat.',
            ],
            [
                'key' => 'actual',
                'label' => 'Biaya aktual komponen wajib sudah lengkap',
                'ok' => $figures['missing_ids'] === [],
                'detail' => $figures['missing_ids'] === [] ? '' : count($figures['missing_ids']).' komponen belum diisi: '.implode(', ', array_slice($figures['missing_names'], 0, 4)).(count($figures['missing_names']) > 4 ? ', dan lainnya' : '').'.',
            ],
            [
                'key' => 'profit_rule',
                'label' => 'Aturan marketing dan bagi hasil aktif tersedia',
                'ok' => $figures['rule_problem'] === null,
                'detail' => $figures['rule_problem'] ?? '',
            ],
        ];
    }

    // ---------------------------------------------------------------- biaya aktual

    /**
     * Menyimpan biaya aktual per komponen. Nilai yang sudah pernah diisi hanya bisa diubah dengan alasan.
     *
     * @param array<int, mixed> $amounts  id komponen => nominal (kosong = belum diisi)
     * @throws \DomainException
     */
    public function recordActualCosts(Trip $trip, array $amounts, ?string $reason): int
    {
        return DB::transaction(function () use ($trip, $amounts, $reason) {
            $fresh = Trip::whereKey($trip->id)->lockForUpdate()->firstOrFail();
            $this->assertActualCostOpen($fresh);

            $reason = trim((string) $reason);
            $plan = [];
            foreach ($fresh->costItems()->active()->get() as $item) {
                if (! array_key_exists($item->id, $amounts)) {
                    continue;
                }

                $raw = $amounts[$item->id];
                $new = ($raw === null || $raw === '') ? null : $this->pricing->normalizeMoney($raw);
                $old = $item->actual_cost === null ? null : (string) $item->actual_cost;

                $same = ($old === null && $new === null)
                    || ($old !== null && $new !== null && bccomp($old, $new, 2) === 0);
                if ($same) {
                    continue;
                }

                if ($old !== null && $reason === '') {
                    throw new \DomainException('Alasan perubahan wajib diisi bila mengubah biaya aktual yang sudah tercatat.');
                }

                $plan[] = [$item, $old, $new];
            }

            foreach ($plan as [$item, $old, $new]) {
                $item->update(['actual_cost' => $new]);
                AuditLogger::record('trip_cost_item', $item->id, 'actual_recorded',
                    ['actual_cost' => $old], ['actual_cost' => $new], $old !== null ? $reason : null);
            }

            return count($plan);
        });
    }

    /**
     * Menambah biaya tambahan yang tidak ada dalam rencana (parkir, denda, dan sebagainya).
     *
     * @throws \DomainException
     */
    public function addAdditionalCost(Trip $trip, string $category, string $name, string $amount, ?int $vendorId, ?string $notes): TripCostItem
    {
        return DB::transaction(function () use ($trip, $category, $name, $amount, $vendorId, $notes) {
            $fresh = Trip::whereKey($trip->id)->lockForUpdate()->firstOrFail();
            $this->assertActualCostOpen($fresh);

            $amount = $this->pricing->normalizeMoney($amount);
            if (bccomp($amount, '0', 2) < 0) {
                throw new \DomainException('Biaya tambahan tidak boleh negatif.');
            }

            $item = $fresh->costItems()->create([
                'item_type' => 'additional',
                'item_id' => 0,
                'vendor_id' => $vendorId,
                'category' => Options::COST_CATEGORIES[$category] ?? $category,
                'item_name' => $name,
                'cost_type' => 'per_trip',
                'cost_behavior' => 'fixed',
                'quantity' => 1,
                'duration_qty' => 1,
                'standard_cost' => 0,
                'negotiated_cost' => null,
                'actual_cost' => $amount,
                'is_required' => false,
                'is_additional' => true,
                'notes' => $notes,
            ]);

            AuditLogger::record('trip_cost_item', $item->id, 'additional_cost_added', null, [
                'trip_id' => $fresh->id,
                'item_name' => $name,
                'actual_cost' => $amount,
            ]);

            return $item;
        });
    }

    /**
     * Membatalkan (void) biaya tambahan. Komponen rencana tidak bisa dibatalkan dari sini.
     *
     * @throws \DomainException
     */
    public function voidAdditionalCost(Trip $trip, int $itemId, string $reason): void
    {
        DB::transaction(function () use ($trip, $itemId, $reason) {
            $fresh = Trip::whereKey($trip->id)->lockForUpdate()->firstOrFail();
            $this->assertActualCostOpen($fresh);

            $item = $fresh->costItems()->active()->where('is_additional', true)->findOrFail($itemId);

            if (trim($reason) === '') {
                throw new \DomainException('Alasan pembatalan wajib diisi.');
            }

            $item->update(['voided_at' => now()]);
            AuditLogger::record('trip_cost_item', $item->id, 'voided',
                ['item_name' => $item->item_name, 'actual_cost' => (string) $item->actual_cost], null, trim($reason));
        });
    }

    // ---------------------------------------------------------------- rekonsiliasi

    /**
     * Rekonsiliasi: membekukan angka keuangan trip menjadi snapshot, lalu mengunci trip.
     * Semua pengecekan dan penghitungan dilakukan ulang di sini, tidak mengandalkan tampilan.
     *
     * @throws \DomainException
     */
    public function reconcile(Trip $trip, bool $extraCostsReviewed, ?string $note): TripSnapshot
    {
        return DB::transaction(function () use ($trip, $extraCostsReviewed, $note) {
            $fresh = Trip::whereKey($trip->id)->lockForUpdate()->firstOrFail();

            if ($fresh->status !== 'completed') {
                throw new \DomainException('Rekonsiliasi hanya untuk trip berstatus Completed.');
            }

            $figures = $this->figures($fresh);
            foreach ($this->checklist($fresh, $figures) as $check) {
                if (! $check['ok']) {
                    throw new \DomainException($check['label'].' belum terpenuhi. '.$check['detail']);
                }
            }
            if (! $extraCostsReviewed) {
                throw new \DomainException('Centang pernyataan bahwa biaya tambahan sudah diperiksa.');
            }

            $note = trim((string) $note);
            $outstanding = $this->pricing->roundMoney($figures['outstanding']);
            if (bccomp($outstanding, '0', 2) > 0 && mb_strlen($note) < 5) {
                throw new \DomainException('Masih ada sisa tagihan. Isi alasan rekonsiliasi (minimal 5 karakter).');
            }

            $split = $figures['split'];

            $r = fn ($v) => $v === null ? null : $this->pricing->roundMoney($v);
            $values = [
                'recommended_price' => $r($figures['recommended_price']),
                'effective_pax' => $fresh->effectivePax(),
                'trip_end_date' => $fresh->end_date->toDateString(),
                'revenue' => $r($figures['revenue']),
                'standard_cost' => $r($figures['standard']),
                'negotiated_cost' => $r($figures['negotiated']),
                'actual_cost' => $r($figures['actual']),
                'vendor_saving' => $r($figures['vendor_saving']),
                'customer_discount' => $r($figures['customer_discount']),
                'actual_profit' => $r($figures['profit']['profit']),
                'marketing_percent' => $split['marketing_percent'],
                'marketing_base' => $split['marketing_base'],
                'marketing_allocation' => $split['marketing_allocation'],
                'distributable_profit' => $split['distributable'],
                'owner_percent' => $split['shares']['owner']['percent'],
                'owner_share' => $split['shares']['owner']['amount'],
                'partner_percent' => $split['shares']['partner']['percent'],
                'partner_share' => $split['shares']['partner']['amount'],
                'profit_rule_set' => $split['rule_set'],
                'outstanding_at_reconcile' => $outstanding,
                'reconcile_note' => $note !== '' ? $note : null,
            ];

            $snapshot = TripSnapshot::create($values + [
                'trip_id' => $fresh->id,
                'reconciled_by' => Auth::id(),
                'reconciled_at' => now(),
            ]);

            foreach (['owner', 'partner'] as $party) {
                ProfitDistribution::create([
                    'trip_id' => $fresh->id,
                    'profit_rule_id' => $split['rules']['shares'][$party]['rule']->id,
                    'party' => $party,
                    'percentage' => $split['shares'][$party]['percent'],
                    'amount' => $split['shares'][$party]['amount'],
                ]);
            }

            $fresh->update(['status' => 'reconciled']);

            AuditLogger::record('trip_snapshot', $snapshot->id, 'created', null, $values + ['trip_id' => $fresh->id], $values['reconcile_note']);
            AuditLogger::record('trip', $fresh->id, 'status_changed',
                ['status' => 'completed'],
                ['status' => 'reconciled', 'extra_costs_reviewed' => true],
                $values['reconcile_note']);

            return $snapshot;
        });
    }

    private function assertActualCostOpen(Trip $trip): void
    {
        if (! in_array($trip->status, Trip::ACTUAL_COST_OPEN, true)) {
            throw new \DomainException('Biaya aktual hanya bisa diisi saat trip berstatus Ongoing atau Completed.');
        }
    }
}