<?php

namespace Tests\Feature;

use App\Livewire\Trip\ReconcilePage;
use App\Models\Destination;
use App\Models\PricingRule;
use App\Models\Trip;
use App\Models\TripCostItem;
use App\Models\TripPricing;
use App\Models\TripSnapshot;
use App\Models\User;
use App\Services\PaymentService;
use App\Services\ReconciliationService;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ReconcileTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;
    private User $staff;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
        $this->owner = $this->userWithRole('owner', true);
        $this->staff = $this->userWithRole('staff');

        // Rekonsiliasi membutuhkan aturan profit yang berlaku: marketing 5% dari Actual Profit, Owner 60%, Partner 40%
        app(\App\Services\ProfitRuleService::class)->createSet([
            'marketing_percent' => '5', 'marketing_base' => 'actual_profit',
            'owner_percent' => '60', 'partner_percent' => '40',
            'effective_from' => today()->toDateString(),
        ], 'Aturan awal untuk tes');
    }

    private function userWithRole(string $role, bool $finance = false): User
    {
        $user = User::factory()->create(['role' => $role, 'financial_visibility' => $finance]);
        $user->assignRole($role);

        return $user;
    }

    /**
     * Contoh dari spesifikasi (4 pax):
     * Standard 7.740.000, Negotiated 6.890.000, Final 9.500.000, Discount 500.000.
     * Komponen A: standar 4.000.000 / nego 3.500.000. Komponen B: standar 3.740.000 / nego 3.390.000.
     *
     * @return array{0: Trip, 1: TripCostItem, 2: TripCostItem}
     */
    private function trip(string $status = 'completed'): array
    {
        $trip = Trip::create([
            'trip_code' => 'SOJ-T-'.uniqid(), 'trip_name' => 'Tes', 'customer_name' => 'Budi',
            'trip_type' => 'private', 'start_date' => '2026-11-01', 'end_date' => '2026-11-03',
            'duration_days' => 3, 'nights' => 2, 'pax' => 4, 'status' => $status, 'created_by' => $this->owner->id,
        ]);

        TripPricing::create([
            'trip_id' => $trip->id, 'pricing_mode' => 'dynamic', 'pricing_method' => 'cost_plus_margin',
            'rate_percent' => 31, 'recommended_price' => 10000000, 'final_price' => 9500000,
            'customer_discount' => 500000,
        ]);

        $base = ['item_type' => 'cost_item', 'item_id' => 1, 'quantity' => 1, 'cost_type' => 'per_trip', 'cost_behavior' => 'fixed'];
        $a = $trip->costItems()->create($base + ['category' => 'Akomodasi', 'item_name' => 'Hotel', 'standard_cost' => 4000000, 'negotiated_cost' => 3500000]);
        $b = $trip->costItems()->create(array_merge($base, ['item_id' => 2, 'category' => 'Transportasi', 'item_name' => 'Bus', 'standard_cost' => 3740000, 'negotiated_cost' => 3390000]));

        return [$trip, $a, $b];
    }

    private function service(): ReconciliationService
    {
        return app(ReconciliationService::class);
    }

    private function fillActuals(Trip $trip, TripCostItem $a, TripCostItem $b, string $actualA = '3550000', string $actualB = '3400000'): void
    {
        $this->service()->recordActualCosts($trip, [$a->id => $actualA, $b->id => $actualB], null);
    }

    private function pay(Trip $trip, string $type, string $amount): void
    {
        app(PaymentService::class)->record($trip, [
            'payment_type' => $type, 'amount' => $amount, 'paid_at' => '2026-10-30', 'method' => 'transfer',
        ]);
    }

    private function assertRejected(callable $action, string $messagePart): void
    {
        try {
            $action();
        } catch (\DomainException $e) {
            $this->assertStringContainsString($messagePart, $e->getMessage());

            return;
        }

        $this->fail("Seharusnya ditolak dengan pesan yang memuat: {$messagePart}");
    }

    public function test_selisih_dan_profit_aktual_sesuai_contoh_spesifikasi(): void
    {
        [$trip, $a, $b] = $this->trip();
        $this->fillActuals($trip, $a, $b); // Actual 6.950.000

        $f = $this->service()->figures($trip);

        $this->assertEquals(7740000, (float) $f['standard']);
        $this->assertEquals(6890000, (float) $f['negotiated']);
        $this->assertEquals(6950000, (float) $f['actual']);
        $this->assertEquals(850000, (float) $f['vendor_saving']);
        $this->assertEquals(60000, (float) $f['variance_negotiated']['amount']);
        $this->assertEquals(-790000, (float) $f['variance_standard']['amount']);
        $this->assertEquals(50000, (float) $f['lines'][$a->id]['variance']['amount']);
        $this->assertEquals(2550000, (float) $f['profit']['profit']); // 9.500.000 - 6.950.000
        $this->assertEquals(637500, (float) $f['profit']['per_pax']);
    }

    public function test_biaya_aktual_hanya_bisa_diisi_saat_ongoing_atau_completed(): void
    {
        foreach (['confirmed', 'deposit_paid', 'paid', 'reconciled', 'cancelled'] as $status) {
            [$trip, $a] = $this->trip($status);
            $this->assertRejected(fn () => $this->service()->recordActualCosts($trip, [$a->id => '100'], null), 'Ongoing atau Completed');
        }

        [$ongoing, $a] = $this->trip('ongoing');
        $this->assertSame(1, $this->service()->recordActualCosts($ongoing, [$a->id => '3550000'], null));
    }

    public function test_mengubah_biaya_aktual_yang_sudah_tercatat_wajib_alasan(): void
    {
        [$trip, $a] = $this->trip();

        $this->service()->recordActualCosts($trip, [$a->id => '3550000'], null); // pertama kali: tanpa alasan

        $this->assertRejected(fn () => $this->service()->recordActualCosts($trip, [$a->id => '3600000'], ''), 'Alasan perubahan wajib');
        $this->assertEquals(3550000, (float) $a->fresh()->actual_cost);

        $this->service()->recordActualCosts($trip, [$a->id => '3600000'], 'Ada tambahan biaya extra bed');
        $this->assertEquals(3600000, (float) $a->fresh()->actual_cost);

        $log = \App\Models\AuditLog::where('entity', 'trip_cost_item')->where('entity_id', $a->id)
            ->where('action', 'actual_recorded')->latest('id')->first();
        $this->assertSame('Ada tambahan biaya extra bed', $log->reason);
        $this->assertEquals(3550000, (float) $log->old_value['actual_cost']);
        $this->assertEquals(3600000, (float) $log->new_value['actual_cost']);
    }

    public function test_biaya_tambahan_menambah_actual_cost_tidak_wajib_dan_bisa_dibatalkan(): void
    {
        [$trip, $a, $b] = $this->trip();
        $this->fillActuals($trip, $a, $b);

        $page = Livewire::actingAs($this->staff)->test(ReconcilePage::class, ['trip' => $trip])
            ->set('extra.category', 'parkir')
            ->set('extra.name', 'Parkir tambahan Ijen')
            ->set('extra.amount', '150000')
            ->call('addExtra')
            ->assertHasNoErrors();

        $extra = $trip->costItems()->where('is_additional', true)->first();
        $this->assertNotNull($extra);
        $this->assertSame('Parkir', $extra->category);
        $this->assertEquals(0, (float) $extra->standard_cost);
        $this->assertEquals(7100000, (float) $this->service()->figures($trip)['actual']); // 6.950.000 + 150.000
        $this->assertSame([], $this->service()->figures($trip)['missing_ids']);

        $page->call('startVoid', $extra->id)
            ->set('voidReason', 'Ternyata sudah termasuk')
            ->call('confirmVoid')
            ->assertHasNoErrors();

        $this->assertNotNull($extra->fresh()->voided_at);
        $this->assertEquals(6950000, (float) $this->service()->figures($trip)['actual']);
    }

    public function test_rekonsiliasi_ditolak_bila_checklist_belum_lengkap(): void
    {
        // Belum Completed
        [$ongoing, $a, $b] = $this->trip('ongoing');
        $this->assertRejected(fn () => $this->service()->reconcile($ongoing, true, null), 'berstatus Completed');

        // Completed tetapi biaya wajib belum diisi
        [$trip, $a, $b] = $this->trip();
        $this->pay($trip, 'deposit', '9500000');
        $this->assertRejected(fn () => $this->service()->reconcile($trip, true, null), 'Biaya aktual komponen wajib sudah lengkap');

        // Satu komponen masih kosong
        $this->service()->recordActualCosts($trip, [$a->id => '3550000'], null);
        $this->assertRejected(fn () => $this->service()->reconcile($trip, true, null), '1 komponen belum diisi');

        // Biaya lengkap, tetapi belum ada pembayaran
        [$noPay, $a2, $b2] = $this->trip();
        $this->fillActuals($noPay, $a2, $b2);
        $this->assertRejected(fn () => $this->service()->reconcile($noPay, true, null), 'Pembayaran sudah dicatat');

        // Lengkap, tetapi pernyataan biaya tambahan belum dicentang
        $this->fillActuals($trip, $a, $b);
        $this->assertRejected(fn () => $this->service()->reconcile($trip, false, null), 'biaya tambahan sudah diperiksa');

        $this->assertSame(0, TripSnapshot::count());
        $this->assertSame('completed', $trip->fresh()->status);
    }

    public function test_rekonsiliasi_dengan_sisa_tagihan_wajib_alasan(): void
    {
        [$trip, $a, $b] = $this->trip();
        $this->fillActuals($trip, $a, $b);
        $this->pay($trip, 'deposit', '3000000'); // sisa 6.500.000

        $this->assertRejected(fn () => $this->service()->reconcile($trip, true, null), 'alasan rekonsiliasi');
        $this->assertRejected(fn () => $this->service()->reconcile($trip, true, 'abc'), 'minimal 5 karakter');
        $this->assertSame(0, TripSnapshot::count());

        $this->service()->reconcile($trip, true, 'Pelunasan dijanjikan minggu depan');

        $snapshot = TripSnapshot::first();
        $this->assertEquals(6500000, (float) $snapshot->outstanding_at_reconcile);
        $this->assertSame('Pelunasan dijanjikan minggu depan', $snapshot->reconcile_note);
    }

    public function test_rekonsiliasi_berhasil_membekukan_angka_dan_mengunci_trip(): void
    {
        [$trip, $a, $b] = $this->trip();
        $this->fillActuals($trip, $a, $b);
        $this->pay($trip, 'deposit', '3000000');
        $this->pay($trip, 'final', '6500000'); // lunas

        $this->actingAs($this->owner);
        $this->service()->reconcile($trip, true, null);

        $snapshot = TripSnapshot::where('trip_id', $trip->id)->first();
        $this->assertEquals(9500000, (float) $snapshot->revenue);
        $this->assertEquals(7740000, (float) $snapshot->standard_cost);
        $this->assertEquals(6890000, (float) $snapshot->negotiated_cost);
        $this->assertEquals(6950000, (float) $snapshot->actual_cost);
        $this->assertEquals(850000, (float) $snapshot->vendor_saving);
        $this->assertEquals(500000, (float) $snapshot->customer_discount);
        $this->assertEquals(2550000, (float) $snapshot->actual_profit);
        $this->assertEquals(0, (float) $snapshot->outstanding_at_reconcile);
        $this->assertSame($this->owner->id, $snapshot->reconciled_by);
        $this->assertEquals(127500, (float) $snapshot->marketing_allocation);      // 5% dari Actual Profit 2.550.000
        $this->assertEquals(2422500, (float) $snapshot->distributable_profit);
        $this->assertEquals(1453500, (float) $snapshot->owner_share);               // 60%
        $this->assertEquals(969000, (float) $snapshot->partner_share);              // 40%

        $this->assertSame('reconciled', $trip->fresh()->status);
        $this->assertDatabaseHas('audit_logs', ['entity' => 'trip_snapshot', 'entity_id' => $snapshot->id, 'action' => 'created']);
        $this->assertDatabaseHas('audit_logs', ['entity' => 'trip', 'entity_id' => $trip->id, 'action' => 'status_changed', 'user_id' => $this->owner->id]);

        // Tidak bisa direkonsiliasi dua kali
        $this->assertRejected(fn () => $this->service()->reconcile($trip, true, null), 'berstatus Completed');
        $this->assertSame(1, TripSnapshot::count());
    }

    public function test_snapshot_tidak_berubah_oleh_perubahan_tarif_aturan_atau_komponen(): void
    {
        [$trip, $a, $b] = $this->trip();
        $this->fillActuals($trip, $a, $b);
        $this->pay($trip, 'deposit', '9500000');
        $this->service()->reconcile($trip, true, null);

        $before = TripSnapshot::first()->only(['revenue', 'standard_cost', 'negotiated_cost', 'actual_cost', 'actual_profit']);

        // Perubahan di masa depan: aturan harga baru, tarif master naik, tarif komponen di trip diubah
        PricingRule::create(['method' => 'cost_plus_markup', 'rate_percent' => 80, 'rounding_rule' => 'exact', 'status' => 'active']);
        $dest = Destination::create(['name' => 'Kawah Ijen', 'standard_cost' => 100000, 'status' => 'active']);
        $dest->update(['standard_cost' => 999999]);
        $a->update(['standard_cost' => 1, 'negotiated_cost' => 1]);
        TripPricing::where('trip_id', $trip->id)->update(['final_price' => 1]);

        $this->assertEquals($before, TripSnapshot::first()->only(['revenue', 'standard_cost', 'negotiated_cost', 'actual_cost', 'actual_profit']));
    }

    public function test_snapshot_tidak_bisa_diubah_atau_dihapus(): void
    {
        [$trip, $a, $b] = $this->trip();
        $this->fillActuals($trip, $a, $b);
        $this->pay($trip, 'deposit', '9500000');
        $this->service()->reconcile($trip, true, null);

        $snapshot = TripSnapshot::first();

        $this->assertFalse($snapshot->update(['revenue' => 1]));
        $this->assertFalse($snapshot->delete());
        $this->assertEquals(9500000, (float) TripSnapshot::first()->revenue);
        $this->assertSame(1, TripSnapshot::count());
    }

    public function test_trip_terkunci_setelah_rekonsiliasi(): void
    {
        [$trip, $a, $b] = $this->trip();
        $this->fillActuals($trip, $a, $b);
        $this->pay($trip, 'deposit', '9500000');
        $this->service()->reconcile($trip, true, null);

        $this->assertRejected(fn () => $this->service()->recordActualCosts($trip, [$a->id => '1'], 'coba ubah'), 'Ongoing atau Completed');
        $this->assertRejected(fn () => $this->pay($trip, 'installment', '1'), 'berstatus Confirmed sampai Completed');
        $this->assertRejected(fn () => $this->service()->addAdditionalCost($trip, 'parkir', 'Parkir', '1000', null, null), 'Ongoing atau Completed');
        $this->assertEquals(3550000, (float) $a->fresh()->actual_cost);

        $page = Livewire::actingAs($this->staff)->test(\App\Livewire\Trip\TripShow::class, ['trip' => $trip]);
        $page->call('changeStatus', 'estimated');
        $this->assertSame('reconciled', $trip->fresh()->status);
    }

    public function test_hak_akses_staff_dan_partner_pada_halaman_rekonsiliasi(): void
    {
        [$trip, $a, $b] = $this->trip();
        $partner = $this->userWithRole('partner');
        $trip->update(['assigned_partner_id' => $partner->id]);
        $this->fillActuals($trip, $a, $b);
        $this->pay($trip, 'deposit', '9500000');

        // Staff: boleh mengisi biaya aktual, tidak boleh merekonsiliasi, tidak melihat profit
        $staffPage = Livewire::actingAs($this->staff)->test(ReconcilePage::class, ['trip' => $trip]);
        $staffPage->set("actual.{$a->id}", '3560000')->set('actualNote', 'Koreksi nota')->call('saveActual')->assertHasNoErrors();
        $this->assertEquals(3560000, (float) $a->fresh()->actual_cost);
        $staffPage->assertDontSee('Actual Profit (Revenue')->assertSee('Actual Cost');
        $staffPage->set('reviewed', true)->call('reconcile')->assertForbidden();
        $this->assertSame('completed', $trip->fresh()->status);

        // Partner tanpa izin keuangan: halaman tertutup
        $this->actingAs($partner)->get(route('trips.reconcile', $trip->id))->assertForbidden();

        // Owner: melihat profit dan bisa merekonsiliasi lewat halaman
        $ownerPage = Livewire::actingAs($this->owner)->test(ReconcilePage::class, ['trip' => $trip]);
        $ownerPage->assertSee('Actual Profit (Revenue');
        $ownerPage->set('reviewed', true)->call('reconcile');
        $this->assertSame('reconciled', $trip->fresh()->status);
        $ownerPage->assertSee('Snapshot keuangan');
    }

    public function test_biaya_aktual_negatif_atau_tidak_valid_ditolak_di_halaman(): void
    {
        [$trip, $a] = $this->trip();

        Livewire::actingAs($this->staff)->test(ReconcilePage::class, ['trip' => $trip])
            ->set("actual.{$a->id}", '-5')
            ->call('saveActual')
            ->assertHasErrors(["actual.{$a->id}"]);

        $this->assertNull($a->fresh()->actual_cost);
    }

    public function test_biaya_tambahan_tidak_mengganggu_halaman_harga_dan_trip(): void
    {
        [$trip, $a, $b] = $this->trip();
        $this->service()->addAdditionalCost($trip, 'parkir', 'Parkir tambahan', '150000', null, null);
        $this->service()->addAdditionalCost($trip, 'tol', 'Tol tambahan', '50000', null, null);
        $trip->update(['status' => 'negotiation']);

        // Tidak dianggap duplikat dan tidak memicu peringatan tarif Rp0
        Livewire::actingAs($this->owner)->test(\App\Livewire\Trip\PricingPage::class, ['trip' => $trip])
            ->assertDontSee('masih bertarif Rp0');
        Livewire::actingAs($this->owner)->test(\App\Livewire\Trip\TripShow::class, ['trip' => $trip])
            ->assertDontSee('2 baris');
    }
}
