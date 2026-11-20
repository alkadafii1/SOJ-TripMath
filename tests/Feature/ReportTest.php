<?php

namespace Tests\Feature;

use App\Livewire\Dashboard\Summary;
use App\Models\ProfitDistribution;
use App\Models\Trip;
use App\Models\TripPricing;
use App\Models\TripSnapshot;
use App\Models\User;
use App\Services\ReportService;
use App\Support\ReportPeriod;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ReportTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;
    private User $staff;
    private Trip $tripA;
    private Trip $tripE;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
        $this->owner = $this->userWithRole('owner', true);
        $this->staff = $this->userWithRole('staff');

        // November 2026: A, B (dengan bagi hasil), C (tanpa bagi hasil). Oktober: D. E belum direkonsiliasi.
        $this->tripA = $this->reconciled('SOJ-A', '2026-11-05', [
            'revenue' => 9500000, 'standard_cost' => 7740000, 'negotiated_cost' => 6890000, 'actual_cost' => 6950000,
            'vendor_saving' => 850000, 'customer_discount' => 500000, 'actual_profit' => 2550000,
            'marketing_allocation' => 475000, 'distributable_profit' => 2075000, 'owner_share' => 1245000, 'partner_share' => 830000,
        ], 10000000);
        $this->reconciled('SOJ-B', '2026-11-20', [
            'revenue' => 10000000, 'standard_cost' => 8000000, 'negotiated_cost' => 7000000, 'actual_cost' => 7200000,
            'vendor_saving' => 1000000, 'customer_discount' => 0, 'actual_profit' => 2800000,
            'marketing_allocation' => 500000, 'distributable_profit' => 2300000, 'owner_share' => 1380000, 'partner_share' => 920000,
        ], 10000000);
        $this->reconciled('SOJ-C', '2026-11-28', [
            'revenue' => 5000000, 'standard_cost' => 4000000, 'negotiated_cost' => 3500000, 'actual_cost' => 3600000,
            'vendor_saving' => 500000, 'customer_discount' => 500000, 'actual_profit' => 1400000,
            'marketing_allocation' => null, 'distributable_profit' => null, 'owner_share' => null, 'partner_share' => null,
        ], 5500000);
        $this->reconciled('SOJ-D', '2026-10-15', [
            'revenue' => 3000000, 'standard_cost' => 2500000, 'negotiated_cost' => 2200000, 'actual_cost' => 2000000,
            'vendor_saving' => 300000, 'customer_discount' => 0, 'actual_profit' => 1000000,
            'marketing_allocation' => 150000, 'distributable_profit' => 850000, 'owner_share' => 510000, 'partner_share' => 340000,
        ], 3000000);

        $this->tripE = $this->makeTrip('SOJ-E', '2026-11-10', 'completed');
    }

    private function userWithRole(string $role, bool $finance = false): User
    {
        $user = User::factory()->create(['role' => $role, 'financial_visibility' => $finance]);
        $user->assignRole($role);

        return $user;
    }

    private function makeTrip(string $code, string $end, string $status = 'reconciled', string $name = null): Trip
    {
        return Trip::create([
            'trip_code' => $code, 'trip_name' => $name ?? "Trip {$code}", 'customer_name' => 'Budi', 'trip_type' => 'private',
            'start_date' => $end, 'end_date' => $end, 'duration_days' => 1, 'nights' => 0, 'pax' => 4,
            'status' => $status, 'created_by' => $this->owner->id,
        ]);
    }

    private function reconciled(string $code, string $end, array $values, int $recommended, ?string $name = null): Trip
    {
        $trip = $this->makeTrip($code, $end, 'reconciled', $name);

        TripPricing::create([
            'trip_id' => $trip->id, 'pricing_mode' => 'dynamic', 'pricing_method' => 'cost_plus_margin', 'rate_percent' => 30,
            'recommended_price' => $recommended, 'final_price' => $values['revenue'],
        ]);

        TripSnapshot::create($values + [
            'trip_id' => $trip->id, 'marketing_percent' => 5, 'marketing_base' => 'revenue', 'owner_percent' => 60, 'partner_percent' => 40,
            'outstanding_at_reconcile' => 0, 'reconciled_by' => $this->owner->id, 'reconciled_at' => now(),
        ]);

        return $trip;
    }

    private function nov(): ReportPeriod
    {
        return ReportPeriod::make('custom', '2026-11-01', '2026-11-30');
    }

    private function service(): ReportService
    {
        return app(ReportService::class);
    }

    private function assertMoney($expected, ?string $actual): void
    {
        $this->assertNotNull($actual);
        $this->assertSame(0, bccomp((string) $expected, $actual, 4), "Diharapkan {$expected}, hasilnya {$actual}");
    }

    public function test_ringkasan_dashboard_periode_november(): void
    {
        $s = $this->service()->summary($this->nov());

        $this->assertSame(3, $s['trips']); // D (Oktober) dan E (belum direkonsiliasi) tidak ikut
        $this->assertMoney(24500000, $s['revenue']);
        $this->assertMoney(19740000, $s['standard_cost']);
        $this->assertMoney(17390000, $s['negotiated_cost']);
        $this->assertMoney(17750000, $s['actual_cost']);
        $this->assertMoney(2350000, $s['vendor_saving']);
        $this->assertMoney(1000000, $s['customer_discount']);
        $this->assertMoney(6750000, $s['actual_profit']);
        $this->assertMoney('27.5510', $s['average_margin']); // 6.750.000 / 24.500.000 (margin gabungan)
        $this->assertMoney(975000, $s['marketing_allocation']);
        $this->assertMoney(4375000, $s['distributable_profit']);
        $this->assertMoney(2625000, $s['owner_share']);
        $this->assertMoney(1750000, $s['partner_share']);
        $this->assertSame(1, $s['trips_without_sharing']); // C
    }

    public function test_filter_periode_dan_periode_kosong(): void
    {
        $oct = $this->service()->summary(ReportPeriod::make('custom', '2026-10-01', '2026-10-31'));
        $this->assertSame(1, $oct['trips']);
        $this->assertMoney(3000000, $oct['revenue']);

        // Hari pertama dan terakhir termasuk dalam rentang
        $edge = $this->service()->summary(ReportPeriod::make('custom', '2026-11-05', '2026-11-05'));
        $this->assertSame(1, $edge['trips']);

        $empty = $this->service()->summary(ReportPeriod::make('custom', '2030-01-01', '2030-01-31'));
        $this->assertSame(0, $empty['trips']);
        $this->assertMoney(0, $empty['revenue']);
        $this->assertNull($empty['average_margin']);

        $counts = $this->service()->statusCounts($this->nov());
        $this->assertSame(['completed' => 1, 'reconciled' => 3], $counts);
    }

    public function test_laporan_profit_trip_dan_selisih_biaya(): void
    {
        $profit = $this->service()->report('trip-profit', $this->nov());
        $this->assertCount(3, $profit['rows']);
        $this->assertSame('SOJ-A', $profit['rows'][0]['trip_code']); // urut tanggal selesai
        $this->assertMoney('26.8421', $profit['rows'][0]['margin']);
        $this->assertMoney(637500, $profit['rows'][0]['profit_per_pax']);
        $this->assertMoney(24500000, $profit['totals']['revenue']);
        $this->assertMoney('27.5510', $profit['totals']['margin']);
        $this->assertMoney(562500, $profit['totals']['profit_per_pax']); // 6.750.000 / 12 pax

        $var = $this->service()->report('cost-variance', $this->nov());
        $this->assertMoney(60000, $var['rows'][0]['variance_negotiated']);
        $this->assertMoney('0.8708', $var['rows'][0]['variance_negotiated_percent']);
        $this->assertMoney(-790000, $var['rows'][0]['variance_standard']);
        $this->assertMoney('-10.2067', $var['rows'][0]['variance_standard_percent']);
        $this->assertMoney(360000, $var['totals']['variance_negotiated']);   // 17.750.000 - 17.390.000
        $this->assertMoney(-1990000, $var['totals']['variance_standard']);   // 17.750.000 - 19.740.000
    }

    public function test_laporan_vendor_saving_dan_customer_discount(): void
    {
        $saving = $this->service()->report('vendor-saving', $this->nov());
        $this->assertMoney('10.9819', $saving['rows'][0]['saving_percent']);
        $this->assertMoney(2350000, $saving['totals']['vendor_saving']);
        $this->assertMoney('11.9047', $saving['totals']['saving_percent']);

        $discount = $this->service()->report('customer-discount', $this->nov());
        $this->assertMoney('5.0000', $discount['rows'][0]['discount_percent']);   // 500.000 / 10.000.000
        $this->assertMoney('0.0000', $discount['rows'][1]['discount_percent']);
        $this->assertMoney('9.0909', $discount['rows'][2]['discount_percent']);   // 500.000 / 5.500.000
        $this->assertMoney(25500000, $discount['totals']['recommended_price']);
        $this->assertMoney(1000000, $discount['totals']['customer_discount']);
        $this->assertMoney('3.9215', $discount['totals']['discount_percent']);
    }

    public function test_laporan_bagi_hasil_melewati_trip_tanpa_pembagian(): void
    {
        $r = $this->service()->report('profit-sharing', $this->nov());

        $this->assertMoney(1245000, $r['rows'][0]['owner_share']);
        $this->assertNull($r['rows'][2]['owner_share']); // C
        $this->assertMoney(2625000, $r['totals']['owner_share']);
        $this->assertMoney(1750000, $r['totals']['partner_share']);
        $this->assertMoney(975000, $r['totals']['marketing_allocation']);
        $this->assertStringContainsString('1 trip', $r['notes'][0]);
    }

    public function test_tren_per_bulan_urut_dari_yang_terlama(): void
    {
        $r = $this->service()->report('trend', ReportPeriod::make('custom', '2026-10-01', '2026-11-30'));

        $this->assertCount(2, $r['rows']);
        $this->assertMoney(3000000, $r['rows'][0]['revenue']);   // Oktober
        $this->assertMoney(24500000, $r['rows'][1]['revenue']);  // November
        $this->assertMoney('27.5510', $r['rows'][1]['margin']);
        $this->assertSame(3, $r['rows'][1]['trips']);
        $this->assertMoney('100.0000', $r['rows'][1]['_bar']);
        $this->assertMoney('12.2448', $r['rows'][0]['_bar']);
        $this->assertSame(4, $r['totals']['trips']);
        $this->assertMoney(27500000, $r['totals']['revenue']);
    }

    public function test_laporan_tidak_dikenal_ditolak(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->service()->report('ngawur', $this->nov());
    }

    public function test_unduh_csv_berisi_baris_data_dan_total(): void
    {
        $res = $this->actingAs($this->owner)->get(route('reports.export', [
            'report' => 'trip-profit', 'period' => 'custom', 'from' => '2026-11-01', 'to' => '2026-11-30',
        ]));

        $res->assertOk();
        $res->assertHeader('content-type', 'text/csv; charset=UTF-8');
        $this->assertStringContainsString('trip-profit_20261101-20261130.csv', $res->headers->get('content-disposition'));

        $csv = $res->streamedContent();
        $this->assertStringStartsWith("\xEF\xBB\xBF", $csv);

        $rows = array_map('str_getcsv', explode("\n", trim(substr($csv, 3))));
        $this->assertSame('Kode', $rows[0][0]);
        $this->assertSame('Revenue', $rows[0][6]);
        $this->assertCount(5, $rows); // judul + 3 trip + total
        $this->assertSame('SOJ-A', $rows[1][0]);
        $this->assertSame('9500000.00', $rows[1][6]);
        $this->assertSame('2026-11-05', $rows[1][4]);
        $this->assertSame('Total', $rows[4][0]);
        $this->assertSame('24500000.00', $rows[4][6]);
    }

    public function test_unduh_untuk_excel_memakai_titik_koma(): void
    {
        $csv = $this->actingAs($this->owner)->get(route('reports.export', [
            'report' => 'vendor-saving', 'period' => 'custom', 'from' => '2026-11-01', 'to' => '2026-11-30', 'format' => 'excel',
        ]))->streamedContent();

        $first = explode("\n", trim(substr($csv, 3)))[1];
        $cells = str_getcsv($first, ';');
        $this->assertSame('SOJ-A', $cells[0]);
        $this->assertSame('850000.00', $cells[5]);
    }

    public function test_csv_menetralkan_rumus_berbahaya_di_teks(): void
    {
        $this->reconciled('SOJ-X', '2026-11-15', [
            'revenue' => 1000000, 'standard_cost' => 900000, 'negotiated_cost' => 800000, 'actual_cost' => 800000,
            'vendor_saving' => 100000, 'customer_discount' => 0, 'actual_profit' => 200000,
        ], 1000000, '=HYPERLINK("http://jahat.test","klik")');

        $csv = $this->actingAs($this->owner)->get(route('reports.export', [
            'report' => 'trip-profit', 'period' => 'custom', 'from' => '2026-11-15', 'to' => '2026-11-15',
        ]))->streamedContent();

        $rows = array_map('str_getcsv', explode("\n", trim(substr($csv, 3))));
        $this->assertSame('\'=HYPERLINK("http://jahat.test","klik")', $rows[1][1]); // diawali petik, bukan rumus
    }

    public function test_hak_akses_laporan_dan_unduhan(): void
    {
        $partnerFinance = $this->userWithRole('partner', true);

        $this->actingAs($this->owner)->get(route('reports'))->assertOk();
        $this->actingAs($this->staff)->get(route('reports'))->assertForbidden();
        $this->actingAs($partnerFinance)->get(route('reports'))->assertForbidden();

        $this->actingAs($this->staff)->get(route('reports.export', ['report' => 'trip-profit']))->assertForbidden();
        $this->actingAs($partnerFinance)->get(route('reports.export', ['report' => 'trip-profit']))->assertForbidden();
        $this->actingAs($this->owner)->get(route('reports.export', ['report' => 'ngawur']))->assertNotFound();
    }

    public function test_dashboard_menampilkan_ringkasan_hanya_untuk_izin_keuangan(): void
    {
        $this->actingAs($this->owner)->get('/dashboard')->assertOk()->assertSee('Average Margin')->assertSee('Ringkasan keuangan');
        $this->actingAs($this->staff)->get('/dashboard')->assertOk()->assertDontSee('Average Margin');

        Livewire::actingAs($this->staff)->test(Summary::class)->assertForbidden();
    }

    public function test_dashboard_mengikuti_filter_periode_dan_menolak_rentang_salah(): void
    {
        $page = Livewire::actingAs($this->owner)->test(Summary::class)
            ->set('preset', 'custom')->set('from', '2026-11-01')->set('to', '2026-11-30');
        $page->assertSee('24.500.000')->assertSee('27,55%');

        $page->set('from', '2026-10-01')->set('to', '2026-10-31')->assertSee('3.000.000');

        $page->set('from', '2026-11-30')->set('to', '2026-11-01')->assertSee('tanggal mulai');
    }

    public function test_halaman_laporan_menampilkan_tabel_dan_berganti_laporan(): void
    {
        Livewire::actingAs($this->owner)->test(\App\Livewire\Report\ReportIndex::class)
            ->set('preset', 'custom')->set('from', '2026-11-01')->set('to', '2026-11-30')
            ->assertSee('Laporan Profit Trip')->assertSee('SOJ-A')->assertSee('Rp 24.500.000')
            ->set('report', 'profit-sharing')->assertSee('Laporan Bagi Hasil')->assertSee('1 trip direkonsiliasi sebelum')
            ->set('report', 'ngawur')->assertSee('Laporan Profit Trip'); // laporan tidak dikenal kembali ke bawaan
    }

    public function test_detail_keuangan_trip(): void
    {
        foreach (['owner' => 1245000, 'partner' => 830000] as $party => $amount) {
            ProfitDistribution::create(['trip_id' => $this->tripA->id, 'party' => $party, 'percentage' => $party === 'owner' ? 60 : 40, 'amount' => $amount]);
        }

        $this->actingAs($this->owner)->get(route('reports.trip', $this->tripA->id))
            ->assertOk()->assertSee('Detail keuangan trip')->assertSee('Pembagian laba')->assertSee('Bagian laba tercatat');

        $this->actingAs($this->owner)->get(route('reports.trip', $this->tripE->id))
            ->assertOk()->assertSee('belum direkonsiliasi');

        $this->actingAs($this->staff)->get(route('reports.trip', $this->tripA->id))->assertForbidden();
    }
}
