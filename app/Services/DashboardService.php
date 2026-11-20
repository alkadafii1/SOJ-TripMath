<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\PricingRule;
use App\Models\Trip;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

/**
 * Data operasional dashboard: apa yang perlu dikerjakan sekarang.
 *
 * Berbeda dari ReportService yang membaca snapshot historis (angka beku), di sini sumbernya
 * trip yang masih berjalan. Semua hitungan uang tetap lewat mesin hitung, dan setiap query
 * dibatasi Trip::visibleTo() supaya Partner hanya melihat trip yang ditugaskan kepadanya.
 */
class DashboardService
{
    /** Status yang dianggap masih aktif (belum selesai dan tidak dibatalkan). */
    private const ACTIVE = ['draft', 'estimated', 'negotiation', 'confirmed', 'deposit_paid', 'paid', 'ongoing', 'completed'];

    public function __construct(
        private FinanceCalculator $finance,
        private PricingCalculator $pricing,
        private TripCalculator $calc,
        private ProfitRuleService $rules,
    ) {
    }

    public function operational(User $user, \App\Support\ReportPeriod $period): array
    {
        $counts = $this->trips($user)
            ->whereDate('start_date', '>=', $period->from->toDateString())
            ->whereDate('start_date', '<=', $period->to->toDateString())
            ->selectRaw('status, count(*) as total')->groupBy('status')->pluck('total', 'status');
        $count = fn (array $statuses) => (int) collect($statuses)->sum(fn ($s) => (int) ($counts[$s] ?? 0));

        return [
            'total' => (int) $counts->sum(),
            'preparation' => $count(['draft', 'estimated', 'negotiation']),
            'ready' => $count(['confirmed', 'deposit_paid', 'paid']),
            'ongoing' => $count(['ongoing']),
            'finished' => $count(['completed', 'reconciled']),
            'completed' => $count(['completed']),
            'reconciled' => $count(['reconciled']),
            'cancelled' => $count(['cancelled']),
            'statuses' => collect(Trip::STATUS)->map(fn ($label, $status) => [
                'status' => $status, 'label' => $label, 'count' => (int) ($counts[$status] ?? 0),
            ])->values()->all(),
        ];
    }

    public function schedule(User $user, string $tab): \Illuminate\Support\Collection
    {
        $query = $this->trips($user)->with(['staff:id,name', 'partner:id,name']);
        if ($tab === 'ongoing') {
            $query->where('status', 'ongoing');
        } else {
            $query->whereIn('status', ['confirmed', 'deposit_paid', 'paid'])
                ->whereDate('start_date', '>=', today()->toDateString())
                ->whereDate('start_date', '<=', today()->addDays(14)->toDateString());
        }

        return $query->orderBy('start_date')->orderBy('id')->limit(10)->get();
    }

    private function trips(User $user): Builder
    {
        return Trip::visibleTo($user);
    }

    /**
     * Jumlah trip per status beserta nilai harga finalnya. Nilai uang hanya untuk yang
     * boleh melihat biaya; jumlah trip boleh dilihat semua peran.
     *
     * @return array{rows: array<int, array<string, mixed>>, active: int, total: int, showMoney: bool}
     */
    public function pipeline(User $user): array
    {
        $counts = $this->trips($user)->selectRaw('status, count(*) as total')->groupBy('status')->pluck('total', 'status');
        $showMoney = $user->can('view-costs');

        $values = collect();
        if ($showMoney) {
            $values = $this->trips($user)
                ->join('trip_pricing', 'trip_pricing.trip_id', '=', 'trips.id')
                ->whereNotNull('trip_pricing.final_price')
                ->selectRaw('trips.status, sum(trip_pricing.final_price) as total')
                ->groupBy('trips.status')
                ->pluck('total', 'trips.status');
        }

        $rows = [];
        foreach (Trip::STATUS as $key => $label) {
            $count = (int) ($counts[$key] ?? 0);
            if ($count === 0) {
                continue;
            }
            $rows[] = [
                'status' => $key,
                'label' => $label,
                'count' => $count,
                'value' => $showMoney ? $this->finance->sum([$values[$key] ?? '0']) : null,
                'stage' => $this->stage($key),
            ];
        }

        $active = 0;
        foreach (self::ACTIVE as $status) {
            $active += (int) ($counts[$status] ?? 0);
        }

        return [
            'rows' => $rows,
            'active' => $active,
            'total' => (int) $counts->sum(),
            'showMoney' => $showMoney,
        ];
    }

    /** Pengelompokan tahap untuk pewarnaan pipeline. */
    private function stage(string $status): string
    {
        return match ($status) {
            'draft', 'estimated', 'negotiation' => 'rencana',
            'confirmed', 'deposit_paid', 'paid' => 'siap',
            'ongoing', 'completed' => 'jalan',
            'reconciled' => 'selesai',
            default => 'batal',
        };
    }

    /**
     * Kartu "perlu tindakan". Tiap kartu membawa jumlah dan beberapa trip contoh dengan
     * tautan langsung ke halaman tempat pekerjaan itu diselesaikan.
     *
     * @return array<int, array<string, mixed>>
     */
    public function actions(User $user): array
    {
        $cards = [];
        $today = today()->toDateString();

        // Persetujuan harga: Owner menyetujui, Staff perlu tahu sedang menunggu
        if ($user->can('pricing.approve') || $user->can('pricing.negotiate')) {
            $cards[] = $this->card(
                'approval',
                $user->can('pricing.approve') ? 'Harga menunggu persetujuan Anda' : 'Harga menunggu persetujuan Owner',
                'Trip tidak bisa dikonfirmasi sampai harga di bawah batas margin disetujui.',
                'pricing',
                $this->trips($user)->where('status', 'negotiation')
                    ->whereHas('pricing', fn ($q) => $q->where('approval_status', 'pending')),
                'amber'
            );
        }

        // Trip selesai tapi belum direkonsiliasi: profit aktual belum bisa dihitung
        $cards[] = $this->card(
            'reconcile',
            'Trip selesai belum direkonsiliasi',
            'Profit aktual dan bagi hasil baru tercatat setelah rekonsiliasi.',
            'reconcile',
            $this->trips($user)->where('status', 'completed'),
            'indigo'
        );

        // Biaya aktual wajib yang masih kosong: penghalang checklist rekonsiliasi
        $cards[] = $this->card(
            'actual_cost',
            'Biaya aktual belum lengkap',
            'Komponen wajib masih kosong, rekonsiliasi akan ditolak.',
            'reconcile',
            $this->trips($user)->whereIn('status', Trip::ACTUAL_COST_OPEN)
                ->whereHas('costItems', fn ($q) => $q->whereNull('voided_at')
                    ->whereNull('actual_cost')->where('is_required', true)->where('is_additional', false)),
            'rose'
        );

        // Penawaran kedaluwarsa: kolom quote_valid_until sudah ada tapi belum dipakai
        $cards[] = $this->card(
            'quote',
            'Penawaran kedaluwarsa',
            'Masa berlaku penawaran lewat tetapi harga final belum disepakati.',
            'pricing',
            $this->trips($user)->whereIn('status', ['estimated', 'negotiation'])
                ->whereHas('pricing', fn ($q) => $q->whereNull('final_price')
                    ->whereNotNull('quote_valid_until')->whereDate('quote_valid_until', '<', $today)),
            'amber'
        );

        // Open Trip di bawah minimum pax: risiko trip tidak jalan
        $cards[] = $this->card(
            'min_pax',
            'Open Trip belum capai minimum',
            'Peserta masih di bawah minimum pax sementara tanggal mulai mendekat.',
            'show',
            $this->trips($user)->where('trip_type', 'open_trip')
                ->whereIn('status', self::ACTIVE)
                ->whereNotNull('min_pax')->where('min_pax', '>', 0)
                ->whereDate('start_date', '>=', $today)
                ->whereDate('start_date', '<=', today()->addDays(14)->toDateString())
                ->where(fn ($q) => $q->whereNull('confirmed_pax')->orWhereColumn('confirmed_pax', '<', 'min_pax')),
            'rose'
        );

        // Trip mulai dekat tapi belum lunas
        $cards[] = $this->card(
            'unpaid',
            'Mulai 7 hari ke depan, belum lunas',
            'Trip segera berjalan tetapi tagihan belum lunas.',
            'payments',
            $this->trips($user)->whereIn('status', ['confirmed', 'deposit_paid'])
                ->whereDate('start_date', '>=', $today)
                ->whereDate('start_date', '<=', today()->addDays(7)->toDateString()),
            'indigo'
        );

        return array_values(array_filter($cards, fn ($c) => $c['count'] > 0));
    }

    /**
     * Membentuk satu kartu. Jumlah dan daftar contoh diambil terpisah supaya tidak
     * memuat seluruh trip ke memori.
     */
    private function card(string $key, string $title, string $desc, string $page, Builder $query, string $tone): array
    {
        $count = (clone $query)->count();
        $trips = $count > 0
            ? (clone $query)->orderBy('start_date')->limit(4)->get(['id', 'trip_code', 'trip_name', 'start_date', 'status', 'pax'])
            : collect();

        return [
            'key' => $key,
            'title' => $title,
            'desc' => $desc,
            'tone' => $tone,
            'count' => $count,
            'trips' => $trips->map(fn (Trip $t) => [
                'id' => $t->id,
                'code' => $t->trip_code,
                'name' => $t->trip_name,
                'start' => $t->start_date,
                'status' => $t->status,
                'url' => $page === 'show' ? route('trips.show', $t->id) : route('trips.'.$page, $t->id),
            ])->all(),
        ];
    }

    /**
     * Jadwal trip 14 hari ke depan. Untuk Partner inilah layar utama: tidak ada angka uang.
     *
     * @return array<int, array<string, mixed>>
     */
    public function upcoming(User $user, int $days = 14): array
    {
        $trips = $this->trips($user)
            ->whereIn('status', ['confirmed', 'deposit_paid', 'paid', 'ongoing'])
            ->whereDate('end_date', '>=', today()->toDateString())
            ->whereDate('start_date', '<=', today()->addDays($days)->toDateString())
            ->orderBy('start_date')->limit(10)
            ->with(['partner:id,name', 'staff:id,name'])
            ->get();

        return $trips->map(fn (Trip $t) => [
            'id' => $t->id,
            'code' => $t->trip_code,
            'name' => $t->trip_name,
            'customer' => $t->customer_name,
            'start' => $t->start_date,
            'days_left' => (int) today()->diffInDays($t->start_date, false),
            'pax' => $t->effectivePax(),
            'nights' => $t->nights,
            'status' => $t->status,
            'partner' => $t->partner?->name,
            'staff' => $t->staff?->name,
            'url' => route('trips.show', $t->id),
        ])->all();
    }

    /**
     * Sisa tagihan seluruh trip aktif. Ini agregat dari data pembayaran yang sudah ada,
     * bukan fitur kas baru: hanya harga final dikurangi pembayaran yang belum di-void.
     *
     * @return array{count: int, outstanding: string, paid: string, final: string}
     */
    public function receivables(User $user): array
    {
        $trips = $this->trips($user)
            ->whereIn('status', Trip::PAYABLE)
            ->whereHas('pricing', fn ($q) => $q->whereNotNull('final_price')->where('final_price', '>', 0))
            ->with(['pricing:trip_id,final_price', 'payments' => fn ($q) => $q->whereNull('voided_at')])
            ->get();

        $finals = [];
        $paids = [];
        $outstandings = [];

        foreach ($trips as $trip) {
            $final = (string) $trip->pricing->final_price;
            $paid = $this->finance->sum($trip->payments->pluck('amount')->all());
            $outstanding = $this->finance->calculateOutstanding($final, $paid);

            if (bccomp($outstanding, '0', FinanceCalculator::SCALE) <= 0) {
                continue;
            }

            $finals[] = $final;
            $paids[] = $paid;
            $outstandings[] = $outstanding;
        }

        return [
            'count' => count($outstandings),
            'final' => $this->finance->sum($finals),
            'paid' => $this->finance->sum($paids),
            'outstanding' => $this->finance->sum($outstandings),
        ];
    }

    /**
     * Estimasi trip berjalan: revenue dan profit perkiraan dari negotiated cost.
     * Sesuai spesifikasi bagian 17, angka ini WAJIB dibedakan dari Actual Profit.
     *
     * @return array{count: int, revenue: string, cost: string, profit: string, margin: ?string}
     */
    public function estimated(User $user): array
    {
        $trips = $this->trips($user)
            ->whereIn('status', ['confirmed', 'deposit_paid', 'paid', 'ongoing'])
            ->whereHas('pricing', fn ($q) => $q->whereNotNull('final_price')->where('final_price', '>', 0))
            ->with(['pricing:trip_id,final_price', 'costItems' => fn ($q) => $q->whereNull('voided_at')])
            ->get();

        $revenues = [];
        $costs = [];

        foreach ($trips as $trip) {
            $items = $trip->costItems->mapWithKeys(fn ($i) => [$i->id => $i->toCalcArray()])->all();
            $revenue = (string) $trip->pricing->final_price;
            $negotiated = $this->calc->calculateTripCost($trip->effectivePax(), $items, 'negotiated_cost', $revenue)['total'];

            $revenues[] = $revenue;
            $costs[] = $negotiated;
        }

        $revenue = $this->finance->sum($revenues);
        $cost = $this->finance->sum($costs);

        return [
            'count' => count($revenues),
            'revenue' => $revenue,
            'cost' => $cost,
            'profit' => $this->finance->calculateProfit($revenue, $cost, 1)['profit'],
            'margin' => $this->pricing->calculateMargin($revenue, $cost),
        ];
    }

    /** Peringatan konfigurasi: aturan harga/profit yang tidak siap dipakai. */
    public function configProblems(User $user): array
    {
        if (! $user->can('rules.manage')) {
            return [];
        }

        $problems = [];
        $today = today()->toDateString();

        $pricingCount = PricingRule::effectiveOn($today)->count();
        if ($pricingCount === 0) {
            $problems[] = ['text' => 'Belum ada aturan harga aktif. Negosiasi vendor tidak bisa dimulai.', 'url' => route('pricing-rules')];
        } elseif ($pricingCount > 1) {
            $problems[] = ['text' => 'Ada '.$pricingCount.' aturan harga aktif bersamaan. Hasil harga bisa tidak konsisten.', 'url' => route('pricing-rules')];
        }

        $ruleProblem = $this->rules->problem();
        if ($ruleProblem !== null) {
            $problems[] = ['text' => $ruleProblem, 'url' => route('profit-rules')];
        }

        return $problems;
    }

    /** Aktivitas terbaru dari audit log, hanya untuk yang berhak melihat audit. */
    public function recentActivity(User $user, int $limit = 6): array
    {
        if (! $user->can('audit.view')) {
            return [];
        }

        return AuditLog::query()->orderByDesc('id')->limit($limit)->get()
            ->map(fn (AuditLog $log) => [
                'entity' => $log->entity,
                'action' => $log->action,
                'reason' => $log->reason,
                'at' => $log->created_at,
            ])->all();
    }
}