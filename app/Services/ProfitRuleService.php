<?php

namespace App\Services;

use App\Models\ProfitRule;
use App\Support\Options;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Aturan marketing allocation dan bagi hasil. Disimpan sebagai satu "paket" (rule_set) berisi
 * satu aturan marketing dan dua aturan bagi hasil (Owner dan Operational Partner), sehingga
 * total bagi hasil 100% selalu dicek bersamaan dan tidak bisa rusak oleh perubahan satu baris.
 */
class ProfitRuleService
{
    public function __construct(private FinanceCalculator $finance)
    {
    }

    /**
     * Paket aturan yang berlaku pada tanggal tertentu. Bila beberapa paket aktif, dipakai yang
     * tanggal berlakunya paling baru. Null bila belum ada paket, atau paket itu tidak lengkap
     * atau total bagi hasilnya bukan 100.
     *
     * @return array{rule_set: string, effective_from: CarbonInterface, effective_to: ?CarbonInterface, marketing: array, shares: array}|null
     */
    public function resolve(?CarbonInterface $date = null): ?array
    {
        $date = ($date ?? now())->toDateString();

        $rows = ProfitRule::where('status', 'active')
            ->whereDate('effective_from', '<=', $date)
            ->where(fn ($q) => $q->whereNull('effective_to')->orWhereDate('effective_to', '>=', $date))
            ->orderByDesc('effective_from')
            ->orderByDesc('id')
            ->get();

        if ($rows->isEmpty()) {
            return null;
        }

        // Hanya paket terbaru yang dipertimbangkan; paket yang rusak tidak boleh diam-diam diganti paket lama
        $latest = $rows->first()->rule_set;

        return $this->assemble($latest, $rows->where('rule_set', $latest));
    }

    /** Alasan aturan tidak bisa dipakai, atau null bila ada aturan yang valid. */
    public function problem(?CarbonInterface $date = null): ?string
    {
        if ($this->resolve($date) !== null) {
            return null;
        }

        return ProfitRule::where('status', 'active')->exists()
            ? 'Aturan profit yang berlaku tidak lengkap atau total bagi hasilnya bukan 100%. Periksa di menu Aturan Profit.'
            : 'Belum ada aturan profit aktif. Owner perlu membuatnya di menu Aturan Profit.';
    }

    private function assemble(string $ruleSet, Collection $group): ?array
    {
        $marketing = $group->firstWhere('rule_type', 'marketing');
        $owner = $group->first(fn ($r) => $r->rule_type === 'profit_sharing' && $r->party === 'owner');
        $partner = $group->first(fn ($r) => $r->rule_type === 'profit_sharing' && $r->party === 'partner');

        if (! $marketing || ! $owner || ! $partner) {
            return null;
        }
        if (! $this->finance->isTotalHundred([(string) $owner->percentage, (string) $partner->percentage])) {
            return null;
        }

        return [
            'rule_set' => $ruleSet,
            'effective_from' => $marketing->effective_from,
            'effective_to' => $marketing->effective_to,
            'marketing' => [
                'rule' => $marketing,
                'percent' => (string) $marketing->percentage,
                'base' => $marketing->calculation_base,
            ],
            'shares' => [
                'owner' => ['rule' => $owner, 'percent' => (string) $owner->percentage],
                'partner' => ['rule' => $partner, 'percent' => (string) $partner->percentage],
            ],
        ];
    }

    /**
     * Membuat paket aturan baru. Paket yang masih berlaku pada tanggal itu diakhiri sehari sebelumnya,
     * dan paket yang tanggal berlakunya sama atau lebih baru dinonaktifkan.
     *
     * @param array{marketing_percent: mixed, marketing_base: string, owner_percent: mixed, partner_percent: mixed, effective_from: string} $data
     * @return string kode paket (rule_set)
     * @throws \DomainException
     */
    public function createSet(array $data, string $reason): string
    {
        $reason = trim($reason);
        if (mb_strlen($reason) < 5) {
            throw new \DomainException('Alasan perubahan wajib diisi (minimal 5 karakter).');
        }

        foreach (['marketing_percent' => 'Persentase marketing', 'owner_percent' => 'Persentase Owner', 'partner_percent' => 'Persentase Partner'] as $key => $label) {
            $value = $data[$key] ?? null;
            if (! is_numeric($value) || bccomp((string) $value, '0', 4) < 0 || bccomp((string) $value, '100', 4) > 0) {
                throw new \DomainException("{$label} harus berupa angka 0 sampai 100.");
            }
        }
        if (! array_key_exists($data['marketing_base'] ?? '', Options::PROFIT_BASES)) {
            throw new \DomainException('Dasar hitung marketing tidak valid.');
        }

        $shares = ['owner' => (string) $data['owner_percent'], 'partner' => (string) $data['partner_percent']];
        if (! $this->finance->isTotalHundred($shares)) {
            throw new \DomainException('Total bagi hasil harus 100% (sekarang '.$this->finance->sumPercent($shares).'%).');
        }

        $from = Carbon::parse($data['effective_from'])->startOfDay();
        if ($from->lt(today())) {
            throw new \DomainException('Tanggal berlaku tidak boleh di masa lalu.');
        }

        return DB::transaction(function () use ($data, $from, $reason) {
            $fromDate = $from->toDateString();
            $dayBefore = $from->copy()->subDay()->toDateString();

            $replaced = ProfitRule::where('status', 'active')
                ->whereDate('effective_from', '>=', $fromDate)
                ->update(['status' => 'inactive']);

            $ended = ProfitRule::where('status', 'active')
                ->whereDate('effective_from', '<', $fromDate)
                ->where(fn ($q) => $q->whereNull('effective_to')->orWhereDate('effective_to', '>=', $fromDate))
                ->update(['effective_to' => $dayBefore]);

            $set = (string) Str::uuid();
            $base = ['rule_set' => $set, 'effective_from' => $fromDate, 'effective_to' => null, 'status' => 'active'];

            $marketing = ProfitRule::create($base + [
                'rule_type' => 'marketing', 'party' => 'marketing',
                'percentage' => $data['marketing_percent'], 'calculation_base' => $data['marketing_base'],
            ]);
            foreach (['owner', 'partner'] as $party) {
                ProfitRule::create($base + [
                    'rule_type' => 'profit_sharing', 'party' => $party,
                    'percentage' => $data["{$party}_percent"], 'calculation_base' => 'distributable_profit',
                ]);
            }

            AuditLogger::record('profit_rule', $marketing->id, 'set_created',
                ['paket_lama_diakhiri' => $ended, 'paket_lama_dinonaktifkan' => $replaced],
                [
                    'rule_set' => $set,
                    'effective_from' => $fromDate,
                    'marketing_percent' => (string) $data['marketing_percent'],
                    'marketing_base' => $data['marketing_base'],
                    'owner_percent' => (string) $data['owner_percent'],
                    'partner_percent' => (string) $data['partner_percent'],
                ],
                $reason);

            return $set;
        });
    }

    /**
     * Menonaktifkan satu paket aturan.
     *
     * @throws \DomainException
     */
    public function deactivateSet(string $ruleSet, string $reason): void
    {
        $reason = trim($reason);
        if (mb_strlen($reason) < 5) {
            throw new \DomainException('Alasan wajib diisi (minimal 5 karakter).');
        }

        DB::transaction(function () use ($ruleSet, $reason) {
            $rows = ProfitRule::where('rule_set', $ruleSet)->where('status', 'active')->get();
            if ($rows->isEmpty()) {
                throw new \DomainException('Paket aturan ini sudah tidak aktif.');
            }

            ProfitRule::where('rule_set', $ruleSet)->update(['status' => 'inactive']);

            AuditLogger::record('profit_rule', $rows->first()->id, 'set_deactivated',
                ['rule_set' => $ruleSet, 'status' => 'active'], ['status' => 'inactive'], $reason);
        });
    }

    /**
     * Riwayat paket aturan, terbaru dulu.
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function sets(): Collection
    {
        $today = today()->toDateString();
        $current = $this->resolve()['rule_set'] ?? null;

        return ProfitRule::orderByDesc('effective_from')->orderByDesc('id')->get()
            ->groupBy('rule_set')
            ->map(function (Collection $group, $set) use ($today, $current) {
                $marketing = $group->firstWhere('rule_type', 'marketing');
                $owner = $group->first(fn ($r) => $r->party === 'owner' && $r->rule_type === 'profit_sharing');
                $partner = $group->first(fn ($r) => $r->party === 'partner' && $r->rule_type === 'profit_sharing');
                $first = $group->first();

                $from = $first->effective_from->toDateString();
                $to = $first->effective_to?->toDateString();

                $state = match (true) {
                    ! $group->every(fn ($r) => $r->status === 'active') => 'inactive',
                    $from > $today => 'scheduled',
                    $to !== null && $to < $today => 'ended',
                    default => 'active',
                };

                return [
                    'rule_set' => (string) $set,
                    'effective_from' => $first->effective_from,
                    'effective_to' => $first->effective_to,
                    'marketing_percent' => $marketing ? (string) $marketing->percentage : null,
                    'marketing_base' => $marketing?->calculation_base,
                    'owner_percent' => $owner ? (string) $owner->percentage : null,
                    'partner_percent' => $partner ? (string) $partner->percentage : null,
                    'state' => $state,
                    'is_current' => $current === (string) $set,
                    'created_at' => $first->created_at,
                ];
            })
            ->values();
    }
}
