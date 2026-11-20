<?php

namespace Tests\Feature;

use App\Livewire\Profit\ProfitRuleIndex;
use App\Livewire\Trip\ReconcilePage;
use App\Models\ProfitDistribution;
use App\Models\ProfitRule;
use App\Models\Trip;
use App\Models\TripPricing;
use App\Models\TripSnapshot;
use App\Models\User;
use App\Services\PaymentService;
use App\Services\ProfitRuleService;
use App\Services\ReconciliationService;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ProfitTest extends TestCase
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
    }

    private function userWithRole(string $role, bool $finance = false): User
    {
        $user = User::factory()->create(['role' => $role, 'financial_visibility' => $finance]);
        $user->assignRole($role);

        return $user;
    }

    private function rules(): ProfitRuleService
    {
        return app(ProfitRuleService::class);
    }

    private function newSet(array $override = [], string $reason = 'Alasan aturan untuk tes'): string
    {
        return $this->rules()->createSet(array_merge([
            'marketing_percent' => '5', 'marketing_base' => 'actual_profit',
            'owner_percent' => '60', 'partner_percent' => '40',
            'effective_from' => today()->toDateString(),
        ], $override), $reason);
    }

    /** Trip Completed, sudah lunas, biaya aktual lengkap, siap direkonsiliasi. */
    private function readyTrip(string $final = '9500000', string $actual = '6950000'): Trip
    {
        $trip = Trip::create([
            'trip_code' => 'SOJ-T-'.uniqid(), 'trip_name' => 'Tes', 'customer_name' => 'Budi',
            'trip_type' => 'private', 'start_date' => '2026-11-01', 'end_date' => '2026-11-03',
            'duration_days' => 3, 'nights' => 2, 'pax' => 4, 'status' => 'completed', 'created_by' => $this->owner->id,
        ]);
        TripPricing::create([
            'trip_id' => $trip->id, 'pricing_mode' => 'dynamic', 'pricing_method' => 'cost_plus_margin',
            'rate_percent' => 30, 'recommended_price' => $final, 'final_price' => $final,
        ]);
        $trip->costItems()->create([
            'item_type' => 'cost_item', 'item_id' => 1, 'quantity' => 1, 'cost_type' => 'per_trip',
            'cost_behavior' => 'fixed', 'category' => 'Akomodasi', 'item_name' => 'Hotel',
            'standard_cost' => $actual, 'negotiated_cost' => $actual, 'actual_cost' => $actual,
        ]);
        app(PaymentService::class)->record($trip, [
            'payment_type' => 'deposit', 'amount' => $final, 'paid_at' => '2026-10-30', 'method' => 'transfer',
        ]);

        return $trip;
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

    public function test_paket_baru_mengakhiri_paket_lama_dan_tercatat_di_audit(): void
    {
        $a = $this->newSet();
        $future = today()->addDays(10);
        $b = $this->newSet(['owner_percent' => '70', 'partner_percent' => '30', 'effective_from' => $future->toDateString()]);

        $this->assertSame(6, ProfitRule::count());
        $this->assertSame($future->copy()->subDay()->toDateString(), ProfitRule::where('rule_set', $a)->first()->effective_to->toDateString());
        $this->assertSame($a, $this->rules()->resolve()['rule_set']);                 // hari ini: paket lama
        $this->assertSame($b, $this->rules()->resolve($future)['rule_set']);         // setelah tanggal berlaku: paket baru
        $this->assertDatabaseHas('audit_logs', ['entity' => 'profit_rule', 'action' => 'set_created', 'reason' => 'Alasan aturan untuk tes']);

        // Paket baru berlaku hari ini menggantikan semua yang tanggalnya sama atau lebih baru
        $c = $this->newSet(['marketing_percent' => '10']);
        $this->assertSame($c, $this->rules()->resolve()['rule_set']);
        $this->assertSame(0, ProfitRule::where('rule_set', $b)->where('status', 'active')->count());
    }

    public function test_aturan_yang_tidak_valid_ditolak(): void
    {
        $this->assertRejected(fn () => $this->newSet(['owner_percent' => '60', 'partner_percent' => '39']), 'Total bagi hasil harus 100%');
        $this->assertRejected(fn () => $this->newSet(['marketing_percent' => '101']), 'Persentase marketing');
        $this->assertRejected(fn () => $this->newSet(['marketing_base' => 'ngawur']), 'Dasar hitung marketing');
        $this->assertRejected(fn () => $this->newSet(['effective_from' => today()->subDay()->toDateString()]), 'masa lalu');
        $this->assertRejected(fn () => $this->newSet([], 'abc'), 'Alasan perubahan wajib');

        $this->assertSame(0, ProfitRule::count());
    }

    public function test_aturan_tidak_berlaku_bila_belum_dimulai_dinonaktifkan_atau_tidak_lengkap(): void
    {
        $this->assertStringContainsString('Belum ada aturan', $this->rules()->problem());

        $future = $this->newSet(['effective_from' => today()->addDays(5)->toDateString()]);
        $this->assertNull($this->rules()->resolve());   // belum mulai berlaku

        $set = $this->newSet();
        $this->assertNotNull($this->rules()->resolve());

        $this->rules()->deactivateSet($set, 'Dibatalkan karena salah isi');
        $this->assertNull($this->rules()->resolve());   // paket masa depan belum berlaku, paket hari ini nonaktif

        // Paket tidak lengkap: satu baris bagi hasil hilang
        $broken = $this->newSet();
        ProfitRule::where('rule_set', $broken)->where('party', 'partner')->delete();
        $this->assertNull($this->rules()->resolve());
        $this->assertStringContainsString('tidak lengkap', $this->rules()->problem());
    }

    public function test_rekonsiliasi_ditolak_tanpa_aturan_dan_berhasil_setelah_aturan_dibuat(): void
    {
        $trip = $this->readyTrip();

        $this->assertRejected(fn () => app(ReconciliationService::class)->reconcile($trip, true, null), 'Aturan marketing dan bagi hasil');
        $this->assertSame(0, TripSnapshot::count());
        $this->assertSame('completed', $trip->fresh()->status);

        $this->newSet();
        app(ReconciliationService::class)->reconcile($trip, true, null);

        $this->assertSame('reconciled', $trip->fresh()->status);
    }

    public function test_skenario_spesifikasi_bagian_46_dengan_marketing_dari_revenue(): void
    {
        $setId = $this->newSet(['marketing_base' => 'revenue']);
        $trip = $this->readyTrip('9500000', '6950000');

        $this->actingAs($this->owner);
        app(ReconciliationService::class)->reconcile($trip, true, null);

        $s = TripSnapshot::where('trip_id', $trip->id)->first();
        $this->assertEquals(2550000, (float) $s->actual_profit);
        $this->assertEquals(475000, (float) $s->marketing_allocation);
        $this->assertEquals(2075000, (float) $s->distributable_profit);
        $this->assertEquals(1245000, (float) $s->owner_share);
        $this->assertEquals(830000, (float) $s->partner_share);
        $this->assertEquals(60, (float) $s->owner_percent);
        $this->assertEquals(40, (float) $s->partner_percent);
        $this->assertSame('revenue', $s->marketing_base);
        $this->assertSame($setId, $s->profit_rule_set);

        $rows = ProfitDistribution::where('trip_id', $trip->id)->get()->keyBy('party');
        $this->assertCount(2, $rows);
        $this->assertEquals(1245000, (float) $rows['owner']->amount);
        $this->assertEquals(830000, (float) $rows['partner']->amount);
        $this->assertNotNull($rows['owner']->profit_rule_id);
        $this->assertEquals((float) $s->distributable_profit, (float) $rows->sum('amount')); // bagian = Distributable Profit
    }

    public function test_hasil_rekonsiliasi_tidak_berubah_walau_aturan_diubah_kemudian(): void
    {
        $this->newSet();
        $trip = $this->readyTrip();
        app(ReconciliationService::class)->reconcile($trip, true, null);

        $before = TripSnapshot::first()->only(['marketing_allocation', 'distributable_profit', 'owner_share', 'partner_share', 'profit_rule_set']);

        $this->newSet(['marketing_percent' => '30', 'owner_percent' => '10', 'partner_percent' => '90']);

        $this->assertEquals($before, TripSnapshot::first()->only(['marketing_allocation', 'distributable_profit', 'owner_share', 'partner_share', 'profit_rule_set']));
        $this->assertEquals(1453500, (float) ProfitDistribution::where('party', 'owner')->first()->amount);

        $row = ProfitDistribution::first();
        $this->assertFalse($row->update(['amount' => 1]));
        $this->assertFalse($row->delete());
        $this->assertSame(2, ProfitDistribution::count());
    }

    public function test_trip_merugi_tetap_bisa_direkonsiliasi_dengan_bagian_negatif(): void
    {
        $this->newSet(); // marketing dari Actual Profit
        $trip = $this->readyTrip('5000000', '6000000'); // rugi 1.000.000

        app(ReconciliationService::class)->reconcile($trip, true, null);

        $s = TripSnapshot::first();
        $this->assertEquals(-1000000, (float) $s->actual_profit);
        $this->assertEquals(0, (float) $s->marketing_allocation);   // tidak ada alokasi dari kerugian
        $this->assertEquals(-1000000, (float) $s->distributable_profit);
        $this->assertEquals(-600000, (float) $s->owner_share);
        $this->assertEquals(-400000, (float) $s->partner_share);
    }

    public function test_pembagian_tampil_hanya_untuk_izin_keuangan(): void
    {
        $this->newSet();
        $trip = $this->readyTrip();

        Livewire::actingAs($this->owner)->test(ReconcilePage::class, ['trip' => $trip])
            ->assertSee('Distributable Profit')
            ->assertSee('Operational Partner');
        Livewire::actingAs($this->staff)->test(ReconcilePage::class, ['trip' => $trip])
            ->assertDontSee('Distributable Profit');

        $page = Livewire::actingAs($this->owner)->test(ReconcilePage::class, ['trip' => $trip])
            ->set('reviewed', true)->call('reconcile');
        $page->assertSee('dibekukan saat rekonsiliasi');
    }

    public function test_halaman_aturan_profit_hanya_untuk_owner_dan_menolak_total_salah(): void
    {
        $this->actingAs($this->staff)->get(route('profit-rules'))->assertForbidden();
        $this->actingAs($this->owner)->get(route('profit-rules'))->assertOk();

        $page = Livewire::actingAs($this->owner)->test(ProfitRuleIndex::class)
            ->set('form.owner_percent', '70')
            ->set('form.partner_percent', '40')
            ->set('form.reason', 'Penyesuaian bagi hasil')
            ->call('save');
        $page->assertSee('Total bagi hasil harus 100%');
        $this->assertSame(0, ProfitRule::count());

        $page->set('form.partner_percent', '30')->call('save')->assertHasNoErrors();
        $this->assertSame(3, ProfitRule::count());
        $page->assertSee('Aturan yang berlaku sekarang');
    }
}
