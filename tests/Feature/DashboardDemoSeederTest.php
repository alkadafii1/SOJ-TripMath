<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\CostItem;
use App\Models\Payment;
use App\Models\ProfitDistribution;
use App\Models\Trip;
use App\Models\TripSnapshot;
use App\Models\User;
use App\Services\DashboardService;
use App\Services\ReportService;
use App\Support\ReportPeriod;
use Database\Seeders\DashboardDemoSeeder;
use Database\Seeders\OwnerSeeder;
use Database\Seeders\ProfitRuleSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\TestCase;

class DashboardDemoSeederTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(now()->setDate(2026, 6, 15)->startOfDay());
        $this->seed([RolePermissionSeeder::class, OwnerSeeder::class, ProfitRuleSeeder::class]);
    }

    public function test_demo_fills_operational_cards_schedule_and_financial_reports(): void
    {
        $staff = User::where('email', 'staff@soj.test')->firstOrFail();
        $this->actingAs($staff);
        $this->seed(DashboardDemoSeeder::class);
        $this->assertAuthenticatedAs($staff);
        $this->assertDatabaseCount('trips', 12);
        $this->assertSame(array_keys(Trip::STATUS), Trip::orderBy('id')->pluck('status')->unique()->values()->all());
        $this->assertDatabaseCount('trip_snapshots', 3);
        $this->assertDatabaseCount('profit_distribution', 6);
        $this->assertDatabaseCount('payments', 7);
        $this->assertDatabaseCount('trip_cost_items', 24);

        $owner = User::where('email', 'owner@soj.test')->firstOrFail();
        $service = app(DashboardService::class);
        $actions = collect($service->actions($owner))->keyBy('key');
        foreach (['approval', 'actual_cost', 'reconcile', 'min_pax', 'unpaid'] as $key) {
            $this->assertGreaterThan(0, $actions[$key]['count']);
        }
        $this->assertCount(1, $service->schedule($owner, 'ongoing'));
        $this->assertCount(3, $service->schedule($owner, 'upcoming'));
        $summary = app(ReportService::class)->summary(ReportPeriod::make('month'));
        $this->assertSame(1, $summary['trips']);
        $this->assertGreaterThan(0, (float) $summary['actual_profit']);
        foreach (TripSnapshot::all() as $snapshot) {
            $this->assertSame(0, bccomp(bcsub($snapshot->revenue, $snapshot->actual_cost, 2), $snapshot->actual_profit, 2));
            $this->assertSame(0, bccomp(bcadd($snapshot->owner_share, $snapshot->partner_share, 2), $snapshot->distributable_profit, 2));
        }
        $this->assertFalse(Payment::whereDate('paid_at', '>', today())->exists());
        $this->assertSame(12, AuditLog::where('action', 'demo_seeded')->count());
    }

    public function test_rerun_preserves_edits_deleted_trips_and_transaction_counts(): void
    {
        $this->seed(DashboardDemoSeeder::class);
        $edited = Trip::where('trip_code', 'DEMO-DASH-DRAFT')->firstOrFail();
        $edited->update(['trip_name' => 'Edited by owner']);
        Trip::where('trip_code', 'DEMO-DASH-ESTIMATED')->firstOrFail()->delete();
        $before = [Payment::count(), TripSnapshot::count(), ProfitDistribution::count(), AuditLog::count()];
        $this->seed(DashboardDemoSeeder::class);
        $this->assertSame('Edited by owner', $edited->fresh()->trip_name);
        $this->assertSame(12, Trip::withTrashed()->count());
        $this->assertSame(11, Trip::count());
        $this->assertSame($before, [Payment::count(), TripSnapshot::count(), ProfitDistribution::count(), AuditLog::count()]);
        $this->assertGuest();
    }

    public function test_production_is_rejected_without_writes(): void
    {
        $this->app->instance('env', 'production');
        try {
            app(DashboardDemoSeeder::class)->run();
            $this->fail('Production demo seeding must be rejected.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('production', $exception->getMessage());
        }
        $this->assertDatabaseCount('trips', 0);
        $this->assertDatabaseCount('cost_items', 0);
    }

    public function test_master_conflict_rolls_back_and_does_not_overwrite_existing_rates(): void
    {
        $master = CostItem::create(['name' => '[DEMO] Konsumsi dashboard', 'category' => 'lainnya',
            'cost_type' => 'per_person', 'cost_behavior' => 'variable', 'standard_cost' => 123, 'status' => 'active']);
        try {
            $this->seed(DashboardDemoSeeder::class);
            $this->fail('A changed demo master must not be overwritten.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('Tidak ditimpa', $exception->getMessage());
        }
        $this->assertDatabaseCount('trips', 0);
        $this->assertDatabaseCount('cost_items', 1);
        $this->assertEquals(123, $master->fresh()->standard_cost);
        $this->assertGuest();
    }

    public function test_first_day_of_month_still_has_current_financial_summary(): void
    {
        $this->travelTo(now()->startOfMonth()->addMonth());
        $this->seed(DashboardDemoSeeder::class);
        $summary = app(ReportService::class)->summary(ReportPeriod::make('month'));
        $this->assertSame(1, $summary['trips']);
        $this->assertGreaterThan(0, (float) $summary['revenue']);
    }
}
