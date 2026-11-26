<?php

namespace Database\Seeders;

use App\Models\CostItem;
use App\Models\Trip;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\PaymentService;
use App\Services\PricingCalculator;
use App\Services\ProfitRuleService;
use App\Services\ReconciliationService;
use App\Services\TripCostService;
use App\Services\TripItemFactory;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class DashboardDemoSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            throw new RuntimeException('DashboardDemoSeeder hanya untuk local/testing; data demo tidak boleh masuk production.');
        }

        $guard = Auth::guard();
        $previousUser = $guard->user();
        try {
            [$created, $skipped] = DB::transaction(function () use ($guard) {
                // Require existing demo users: never create or change business accounts here.
                $users = [];
                foreach (['owner', 'staff', 'partner'] as $role) {
                    $user = User::where('email', $role.'@soj.test')->first();
                    if (! $user || ! $user->hasRole($role) || $user->status !== 'active') {
                        throw new RuntimeException('Akun demo '.$role.' tidak siap. Jalankan seeder dasar dan periksa akun @soj.test terlebih dahulu.');
                    }
                    $users[$role] = $user;
                }
                if ($problem = app(ProfitRuleService::class)->problem()) {
                    throw new RuntimeException('Aturan bagi hasil belum siap: '.$problem.' Seeder tidak mengubah aturan bisnis.');
                }
                $guard->setUser($users['owner']);

                $masters = collect([
                    ['name' => '[DEMO] Transport dashboard', 'category' => 'operasional', 'cost_type' => 'per_trip', 'standard_cost' => '2000000'],
                    ['name' => '[DEMO] Konsumsi dashboard', 'category' => 'lainnya', 'cost_type' => 'per_person', 'standard_cost' => '100000'],
                ])->map(function (array $attributes) {
                    $master = CostItem::withTrashed()->firstOrCreate(['name' => $attributes['name']], $attributes + [
                        'status' => 'active', 'cost_behavior' => $attributes['cost_type'] === 'per_person' ? 'variable' : 'fixed',
                        'attributes' => ['demo' => true, 'purpose' => 'Simulasi dashboard, bukan tarif vendor sebenarnya.'],
                    ]);
                    if ($master->trashed() || $master->status !== 'active'
                        || $master->cost_type !== $attributes['cost_type']
                        || bccomp((string) $master->standard_cost, $attributes['standard_cost'], 2) !== 0) {
                        throw new RuntimeException('Master demo telah diubah/nonaktif: '.$master->name.'. Tidak ditimpa oleh seeder.');
                    }

                    return $master;
                });

                $created = $skipped = 0;
                foreach ($this->scenarios() as $key => $scenario) {
                    $code = 'DEMO-DASH-'.strtoupper($key);
                    if (Trip::withTrashed()->where('trip_code', $code)->exists()) {
                        $skipped++;

                        continue;
                    }
                    $trip = Trip::create([
                        'trip_code' => $code, 'trip_name' => '[DEMO] '.$scenario['name'],
                        'customer_name' => 'Customer Simulasi '.($created + 1),
                        'trip_type' => $key === 'confirmed' ? 'open_trip' : 'private',
                        'start_date' => $scenario['start'], 'end_date' => $scenario['end'],
                        'duration_days' => 2, 'nights' => 1, 'pax' => 8,
                        'min_pax' => $key === 'confirmed' ? 10 : null,
                        'max_capacity' => $key === 'confirmed' ? 16 : null,
                        'confirmed_pax' => $key === 'confirmed' ? 8 : null,
                        'status' => $scenario['status'] === 'reconciled' ? 'completed' : $scenario['status'],
                        'created_by' => $users['owner']->id,
                        'assigned_staff_id' => $users['staff']->id,
                        'assigned_partner_id' => $users['partner']->id,
                        'itinerary' => "Hari 1: Penjemputan dan wisata Banyuwangi.\nHari 2: Wisata Ijen dan perjalanan pulang.",
                        'inclusions' => 'Simulasi transportasi dan konsumsi.',
                        'exclusions' => 'Pengeluaran pribadi.',
                        'notes' => '[DEMO DASHBOARD] Data sintetis; jangan digunakan sebagai transaksi atau laporan bisnis asli.',
                        'cancel_reason' => $scenario['status'] === 'cancelled' ? 'Pembatalan simulasi dashboard.' : null,
                    ]);
                    foreach ($masters as $master) {
                        $trip->costItems()->create(TripItemFactory::make('cost_item', $master, $trip) + [
                            'quantity' => 1, 'day_no' => 1, 'is_required' => true,
                            'negotiated_cost' => bcmul((string) $master->standard_cost, '0.9', 2),
                        ]);
                    }
                    $costs = app(TripCostService::class);
                    $standard = $costs->recalculate($trip);
                    $negotiated = $costs->summary($trip, 'negotiated_cost');
                    $pricing = app(PricingCalculator::class);
                    if (! in_array($scenario['status'], ['draft', 'cancelled'], true)) {
                        $recommended = $pricing->calculateRecommendedPrice($standard['total'], 'cost_plus_markup', '30');
                        $final = bcsub($recommended, '100000', 2);
                        $agreed = ! in_array($scenario['status'], ['estimated', 'negotiation'], true);
                        $trip->pricing()->create([
                            'pricing_mode' => 'dynamic', 'pricing_method' => 'cost_plus_markup', 'rate_percent' => 30,
                            'recommended_price' => $recommended, 'quoted_price' => $recommended,
                            'final_price' => $agreed ? $final : null,
                            'customer_discount' => $agreed ? $pricing->calculateCustomerDiscount($recommended, $final) : null,
                            'customer_requested_price' => $scenario['status'] === 'negotiation'
                                ? bcmul($negotiated['total'], '1.05', 2) : null,
                            'approval_status' => $scenario['status'] === 'negotiation' ? 'pending' : 'none',
                            'require_approval' => $scenario['status'] === 'negotiation',
                            'min_margin_threshold' => 15,
                            'below_min_reason' => $scenario['status'] === 'negotiation' ? 'Simulasi permintaan harga di bawah margin minimum.' : null,
                            'quote_valid_until' => today()->addDays(7),
                            'payment_terms' => 'Simulasi: DP 30%, pelunasan sebelum keberangkatan.',
                        ]);
                        if (in_array($scenario['status'], ['deposit_paid', 'paid', 'ongoing', 'completed', 'reconciled'], true)) {
                            app(PaymentService::class)->record($trip, [
                                'payment_type' => $scenario['status'] === 'deposit_paid' ? 'deposit' : 'final',
                                'amount' => $scenario['status'] === 'deposit_paid' ? bcmul($final, '0.3', 2) : $final,
                                'paid_at' => $scenario['start']->copy()->subDay()->min(today())->toDateString(),
                                'method' => 'transfer', 'reference' => $code,
                                'notes' => '[DEMO] Pembayaran sintetis, bukan penerimaan uang sebenarnya.',
                            ]);
                        }
                    }
                    if (in_array($scenario['status'], ['ongoing', 'reconciled'], true)) {
                        $actual = [];
                        foreach ($negotiated['lines'] as $id => $line) {
                            $actual[$id] = bcmul($line['amount'], $key === 'history-2' ? '1.05' : '0.98', 2);
                        }
                        app(ReconciliationService::class)->recordActualCosts($trip, $actual, null);
                    }
                    if ($scenario['status'] === 'reconciled') {
                        app(ReconciliationService::class)->reconcile($trip, true, '[DEMO] Rekonsiliasi untuk contoh dashboard.');
                    }
                    AuditLogger::record('trip', $trip->id, 'demo_seeded', null, ['trip_code' => $code], 'Data sintetis dashboard.');
                    $created++;
                }

                return [$created, $skipped];
            });
        } finally {
            $previousUser ? $guard->setUser($previousUser) : $guard->forgetUser();
        }
        $this->command?->info("Dashboard demo: {$created} trip dibuat; {$skipped} trip yang sudah ada/dihapus dilewati.");
    }

    private function scenarios(): array
    {
        $rows = [];
        foreach (Trip::STATUS as $status => $label) {
            $start = match ($status) {
                'ongoing' => today()->subDay(),
                'completed', 'reconciled' => today()->subDays(2),
                default => today()->addDays(3),
            };
            $rows[$status] = ['name' => $label.' - Banyuwangi', 'status' => $status, 'start' => $start, 'end' => $start->copy()->addDay()];
        }
        // Keep current-month revenue visible even when seeded on the first day of a month.
        $rows['reconciled']['start'] = today()->startOfMonth();
        $rows['reconciled']['end'] = today()->startOfMonth()->addDay();
        if (today()->day === 1) {
            $rows['reconciled']['start'] = today()->subDay();
            $rows['reconciled']['end'] = today();
        }
        foreach ([1, 2] as $months) {
            $start = today()->startOfMonth()->subMonths($months)->addDays(9);
            $rows['history-'.$months] = ['name' => 'Rekonsiliasi historis '.$months.' bulan lalu', 'status' => 'reconciled',
                'start' => $start, 'end' => $start->copy()->addDay()];
        }

        return $rows;
    }
}
