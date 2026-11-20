<?php

namespace Tests\Feature;

use App\Livewire\Dashboard\Overview;
use App\Models\Trip;
use App\Models\User;
use App\Services\DashboardService;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Dashboard operasional: angka tindakan, posisi trip, dan pembatasan per peran.
 * Semua tanggal memakai today() supaya test tidak bergantung tanggal berjalan.
 */
class DashboardOverviewTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;
    private User $staff;
    private User $partner;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);

        $this->owner = $this->makeUser('owner', true);
        $this->staff = $this->makeUser('staff');
        $this->partner = $this->makeUser('partner');
    }

    private function makeUser(string $role, bool $finance = false): User
    {
        $user = User::factory()->create(['role' => $role, 'financial_visibility' => $finance]);
        $user->assignRole($role);

        return $user;
    }

    private function trip(array $attrs = []): Trip
    {
        return Trip::create(array_merge([
            'trip_code' => 'SOJ-'.uniqid(),
            'trip_name' => 'Banyuwangi Ijen',
            'customer_name' => 'Nashir',
            'trip_type' => 'private',
            'start_date' => today()->addDays(3)->toDateString(),
            'end_date' => today()->addDays(4)->toDateString(),
            'duration_days' => 2,
            'nights' => 1,
            'pax' => 4,
            'status' => 'confirmed',
            'created_by' => $this->owner->id,
        ], $attrs));
    }

    public function test_kartu_tindakan_menghitung_trip_yang_perlu_dikerjakan(): void
    {
        $this->trip(['status' => 'completed']);
        $this->trip(['status' => 'completed']);
        $this->trip(['status' => 'ongoing']);

        $actions = collect(app(DashboardService::class)->actions($this->owner))->keyBy('key');

        $this->assertSame(2, $actions['reconcile']['count']);
        $this->assertStringContainsString('/reconcile', $actions['reconcile']['trips'][0]['url']);
    }

    public function test_pipeline_menghitung_trip_per_status_dan_menyembunyikan_nilai_uang(): void
    {
        $this->trip(['status' => 'draft']);
        $this->trip(['status' => 'confirmed']);
        $this->trip(['status' => 'confirmed']);

        $pipeline = app(DashboardService::class)->pipeline($this->owner);
        $counts = collect($pipeline['rows'])->pluck('count', 'status');

        $this->assertSame(1, $counts['draft']);
        $this->assertSame(2, $counts['confirmed']);
        $this->assertSame(3, $pipeline['active']);
        $this->assertTrue($pipeline['showMoney']);

        // Partner tidak punya izin biaya, jadi nilai rupiah tidak boleh muncul
        $asPartner = app(DashboardService::class)->pipeline($this->partner);
        $this->assertFalse($asPartner['showMoney']);
        $this->assertNull($asPartner['rows'][0]['value'] ?? null);
    }

    public function test_partner_hanya_melihat_trip_yang_ditugaskan(): void
    {
        $this->trip(['assigned_partner_id' => $this->partner->id]);
        $this->trip(['assigned_partner_id' => $this->owner->id]);

        $service = app(DashboardService::class);

        $this->assertSame(1, $service->pipeline($this->partner)['total']);
        $this->assertCount(1, $service->upcoming($this->partner));
        $this->assertSame(2, $service->pipeline($this->owner)['total']);
    }

    public function test_dashboard_staff_tanpa_angka_keuangan(): void
    {
        $this->trip(['status' => 'completed']);

        Livewire::actingAs($this->staff)->test(Overview::class)
            ->assertOk()
            ->assertSee('Perlu perhatian')
            ->assertSee('Sebaran status trip')
            ->assertDontSee('Perkiraan profit')
            ->assertDontSee('Average Margin');
    }

    public function test_dashboard_owner_tanpa_panel_estimasi_dan_tagihan(): void
    {
        $trip = $this->trip(['status' => 'deposit_paid']);
        $trip->pricing()->create([
            'pricing_mode' => 'total',
            'pricing_method' => 'margin_on_cost',
            'rate_percent' => '30.0000',
            'recommended_price' => '10000000.00',
            'final_price' => '9500000.00',
        ]);
        $trip->payments()->create([
            'payment_type' => 'deposit',
            'amount' => '2000000.00',
            'paid_at' => today()->toDateString(),
            'method' => 'transfer',
        ]);

        $receivables = app(DashboardService::class)->receivables($this->owner);
        $this->assertSame(1, $receivables['count']);
        $this->assertSame(0, bccomp('7500000.00', $receivables['outstanding'], 2));

        Livewire::actingAs($this->owner)->test(Overview::class)
            ->assertOk()
            ->assertDontSee('Tagihan belum lunas')
            ->assertDontSee('Rp 7.500.000')
            ->assertDontSee('Perkiraan trip berjalan')
            ->assertSee('Average Margin');
    }

    public function test_jadwal_memuat_trip_yang_sudah_dimulai_dan_masih_berlangsung(): void
    {
        $running = $this->trip([
            'status' => 'ongoing',
            'start_date' => today()->subDays(2)->toDateString(),
            'end_date' => today()->addDay()->toDateString(),
            'assigned_partner_id' => $this->partner->id,
        ]);
        $this->trip([
            'start_date' => today()->subDays(5)->toDateString(),
            'end_date' => today()->subDays(3)->toDateString(),
        ]);
        $rows = app(DashboardService::class)->upcoming($this->partner);
        $this->assertCount(1, $rows);
        $this->assertSame($running->id, $rows[0]['id']);

        Livewire::actingAs($this->partner)->test(Overview::class)
            ->assertSee('Banyuwangi Ijen')
            ->assertSee('Jadwal trip')
            ->assertDontSee('Alur kerja singkat')
            ->assertDontSee('Perkiraan profit');
    }

    public function test_open_trip_jauh_di_masa_depan_tidak_memicu_peringatan_minimum(): void
    {
        $this->trip([
            'trip_type' => 'open_trip',
            'min_pax' => 10,
            'confirmed_pax' => 2,
            'start_date' => today()->addDays(30)->toDateString(),
            'end_date' => today()->addDays(31)->toDateString(),
        ]);
        $actions = collect(app(DashboardService::class)->actions($this->owner))->keyBy('key');
        $this->assertFalse($actions->has('min_pax'));
    }

    public function test_ringkasan_operasional_mengikuti_tanggal_mulai_dan_scope_partner(): void
    {
        $this->trip(['status' => 'draft', 'start_date' => '2026-05-01']);
        $this->trip(['status' => 'completed', 'start_date' => '2026-05-03', 'assigned_partner_id' => $this->partner->id]);
        $this->trip(['status' => 'reconciled', 'start_date' => '2026-05-04']);
        $this->trip(['status' => 'cancelled', 'start_date' => '2026-05-05']);
        $this->trip(['status' => 'ongoing', 'start_date' => '2026-06-01']);
        $period = \App\Support\ReportPeriod::make('custom', '2026-05-01', '2026-05-31');
        $service = app(DashboardService::class);
        $stats = $service->operational($this->owner, $period);
        $this->assertSame(4, $stats['total']);
        $this->assertSame(1, $stats['preparation']);
        $this->assertSame(2, $stats['finished']);
        $this->assertSame(1, $stats['cancelled']);
        $this->assertSame(1, $service->operational($this->partner, $period)['total']);
    }

    public function test_tab_jadwal_memisahkan_trip_ongoing_dan_keberangkatan(): void
    {
        $this->trip(['status' => 'ongoing', 'trip_name' => 'Trip Sedang Jalan']);
        $this->trip(['status' => 'paid', 'trip_name' => 'Trip Akan Berangkat']);
        Livewire::actingAs($this->staff)->test(Overview::class)
            ->assertSee('Trip Sedang Jalan')->assertDontSee('Trip Akan Berangkat')
            ->set('tab', 'upcoming')->assertSee('Trip Akan Berangkat')->assertDontSee('Trip Sedang Jalan');
    }

    public function test_halaman_dashboard_memuat_dashboard_baru_dan_ringkasan_lama(): void
    {
        $this->trip(['status' => 'completed']);

        $this->actingAs($this->owner)->get('/dashboard')
            ->assertOk()
            ->assertSee('Sebaran status trip')
            ->assertSee('Ringkasan keuangan');

        $this->actingAs($this->partner)->get('/dashboard')
            ->assertOk()
            ->assertDontSee('Average Margin');
    }
}
