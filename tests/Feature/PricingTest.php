<?php

namespace Tests\Feature;

use App\Livewire\Trip\PricingPage;
use App\Models\PricingRule;
use App\Models\Trip;
use App\Models\TripPricing;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class PricingTest extends TestCase
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

    private function rule(array $overrides = []): PricingRule
    {
        return PricingRule::create(array_merge([
            'method' => 'cost_plus_margin', 'rate_percent' => 30, 'rounding_rule' => 'nearest_100000',
            'min_margin_threshold' => 25, 'block_below_min' => false, 'require_approval' => true,
            'status' => 'active',
        ], $overrides));
    }

    /**
     * Trip dengan dua biaya (4 pax):
     *  - Bus per trip, fixed: standar 5.000.000, negosiasi 4.500.000
     *  - Tiket per orang, variable: standar 500.000, negosiasi 450.000
     * Standard 7.000.000, Negotiated 6.300.000. Recommended (margin 30%, bulatkan 100rb) = 9.000.000.
     */
    private function trip(array $overrides = []): Trip
    {
        $trip = Trip::create(array_merge([
            'trip_code' => 'SOJ-T-'.uniqid(), 'trip_name' => 'Tes', 'customer_name' => 'Budi',
            'trip_type' => 'private', 'start_date' => '2026-11-01', 'end_date' => '2026-11-02',
            'duration_days' => 2, 'pax' => 4, 'status' => 'estimated', 'created_by' => $this->owner->id,
        ], $overrides));

        $base = ['item_type' => 'cost_item', 'item_id' => 1, 'quantity' => 1];
        $trip->costItems()->create($base + [
            'category' => 'Transport', 'item_name' => 'Bus', 'cost_type' => 'per_trip',
            'cost_behavior' => 'fixed', 'standard_cost' => 5000000, 'negotiated_cost' => 4500000,
        ]);
        $trip->costItems()->create($base + [
            'category' => 'Tiket', 'item_name' => 'Tiket', 'cost_type' => 'per_person',
            'cost_behavior' => 'variable', 'standard_cost' => 500000, 'negotiated_cost' => 450000,
        ]);

        return $trip;
    }

    private function page(User $user, Trip $trip)
    {
        return Livewire::actingAs($user)->test(PricingPage::class, ['trip' => $trip]);
    }

    private function negotiating(User $user, Trip $trip)
    {
        return $this->page($user, $trip)->call('startNegotiation')->assertHasNoErrors();
    }

    public function test_negosiasi_vendor_mengubah_negotiated_tapi_standard_tetap(): void
    {
        $trip = $this->trip();
        $bus = $trip->costItems()->where('item_name', 'Bus')->first();

        $this->page($this->staff, $trip)
            ->set("negotiated.{$bus->id}", '4000000')
            ->set('negotiationNote', 'Nego langsung')
            ->call('saveNegotiatedCosts')
            ->assertHasNoErrors();

        $fresh = $bus->fresh();
        $this->assertEquals(4000000, (float) $fresh->negotiated_cost);
        $this->assertEquals(5000000, (float) $fresh->standard_cost);
        $this->assertDatabaseHas('audit_logs', [
            'entity' => 'trip_cost_item', 'entity_id' => $bus->id,
            'action' => 'negotiated', 'reason' => 'Nego langsung',
        ]);
    }

    public function test_mulai_negosiasi_menyimpan_harga_rekomendasi_dan_snapshot_aturan(): void
    {
        $rule = $this->rule();
        $trip = $this->trip();

        $this->negotiating($this->staff, $trip);

        $this->assertSame('negotiation', $trip->fresh()->status);
        $pricing = TripPricing::where('trip_id', $trip->id)->first();
        $this->assertEquals(9000000, (float) $pricing->recommended_price);
        $this->assertEquals(30, (float) $pricing->rate_percent);

        // Aturan berubah, trip yang sedang berjalan tidak ikut berubah
        $rule->update(['rate_percent' => 50]);
        $this->assertEquals(30, (float) $pricing->fresh()->rate_percent);
    }

    public function test_negosiasi_bisa_dimulai_dari_draft_tanpa_pindah_halaman(): void
    {
        $this->rule();
        $draft = $this->trip(['status' => 'draft']);
        $bus = $draft->costItems()->where('item_name', 'Bus')->first();
        $this->page($this->staff, $draft)
            ->assertSee('Negosiasi harga customer')
            ->assertSee('Lanjut ke Negosiasi Customer')
            ->assertDontSee('Tandai trip sebagai Estimated di halaman trip')
            ->set("negotiated.{$bus->id}", '4000000')
            ->call('startNegotiation')->assertHasNoErrors()
            ->assertSee('Harga yang diminta customer')
            ->assertSee('Revisi Estimasi');
        $this->assertSame('negotiation', $draft->fresh()->status);
        $this->assertEquals(4000000, (float) $bus->fresh()->negotiated_cost);
        $this->assertEquals(8300000, (float) TripPricing::first()->recommended_price);
        $logs = \App\Models\AuditLog::where('entity', 'trip')->where('entity_id', $draft->id)
            ->where('action', 'status_changed')->orderBy('id')->get();
        $this->assertCount(2, $logs);
    }

    public function test_mulai_negosiasi_tanpa_aturan_tidak_mengubah_status(): void
    {
        $draft = $this->trip(['status' => 'draft']);
        $this->page($this->staff, $draft)->call('startNegotiation');
        $this->assertSame('draft', $draft->fresh()->status);
        $this->assertSame(0, TripPricing::count());

        $estimated = $this->trip();
        $this->page($this->staff, $estimated)->call('startNegotiation');
        $this->assertSame('estimated', $estimated->fresh()->status);
    }

    public function test_estimasi_kosong_dan_tarif_invalid_tidak_bisa_dilanjutkan(): void
    {
        $this->rule();
        $draft = $this->trip(['status' => 'draft']);
        $draft->costItems()->update(['voided_at' => now()]);
        $this->page($this->staff, $draft)->call('startNegotiation')
            ->assertSee('Tambahkan minimal satu komponen biaya');
        $this->assertSame('draft', $draft->fresh()->status);

        $trip = $this->trip(['status' => 'draft']);
        $bus = $trip->costItems()->first();
        $this->page($this->staff, $trip)->set("negotiated.{$bus->id}", '-1')
            ->call('startNegotiation')->assertHasErrors(["negotiated.{$bus->id}"]);
        $this->assertSame('draft', $trip->fresh()->status);
        $this->assertSame(0, TripPricing::count());
    }

    public function test_harga_rekomendasi_nol_membatalkan_perubahan_vendor_dan_status(): void
    {
        $this->rule();
        $trip = $this->trip(['status' => 'draft']);
        $page = $this->page($this->staff, $trip);
        foreach ($trip->costItems as $item) {
            $page->set("negotiated.{$item->id}", '0');
        }
        $page->call('startNegotiation')->assertHasErrors(['negotiationReady']);
        $this->assertSame('draft', $trip->fresh()->status);
        $this->assertSame(0, TripPricing::count());
        $this->assertEquals(4500000, (float) $trip->costItems()->where('item_name', 'Bus')->first()->negotiated_cost);
        $this->assertDatabaseMissing('audit_logs', ['entity' => 'trip_cost_item', 'action' => 'negotiated']);
    }

    public function test_trip_yang_sudah_dikonfirmasi_tidak_bisa_memulai_negosiasi_ulang(): void
    {
        $this->rule();
        $trip = $this->trip(['status' => 'confirmed']);
        $this->page($this->staff, $trip)->call('startNegotiation');
        $this->assertSame('confirmed', $trip->fresh()->status);
        $this->assertSame(0, TripPricing::count());
    }

    public function test_harga_final_tidak_menimpa_recommended_dan_diskon_dihitung(): void
    {
        $this->rule();
        $trip = $this->trip();

        $this->negotiating($this->staff, $trip)
            ->set('price.quoted', '9200000')
            ->set('price.final', '8500000')
            ->call('saveNegotiation')
            ->assertHasNoErrors();

        $pricing = TripPricing::first();
        $this->assertEquals(9000000, (float) $pricing->recommended_price);
        $this->assertEquals(9200000, (float) $pricing->quoted_price);
        $this->assertEquals(8500000, (float) $pricing->final_price);
        $this->assertEquals(500000, (float) $pricing->customer_discount);
    }

    public function test_ubah_harga_final_wajib_alasan_dan_tercatat(): void
    {
        $this->rule();
        $trip = $this->trip();

        $component = $this->negotiating($this->staff, $trip)
            ->set('price.final', '8500000')
            ->call('saveNegotiation')
            ->assertHasNoErrors();

        $component->set('price.final', '8600000')
            ->call('saveNegotiation')
            ->assertHasErrors(['price.change_reason']);
        $this->assertEquals(8500000, (float) TripPricing::first()->final_price);

        $component->set('price.change_reason', 'Customer minta paket tambahan')
            ->call('saveNegotiation')
            ->assertHasNoErrors();

        $this->assertEquals(8600000, (float) TripPricing::first()->final_price);
        $this->assertDatabaseHas('audit_logs', [
            'entity' => 'trip_pricing', 'action' => 'updated', 'reason' => 'Customer minta paket tambahan',
        ]);
    }

    public function test_di_bawah_batas_minimum_butuh_persetujuan_owner(): void
    {
        $this->rule();
        $trip = $this->trip();

        // Final 8.000.000 => margin 21,25% (di bawah batas 25%)
        $staffPage = $this->negotiating($this->staff, $trip)
            ->set('price.final', '8000000')
            ->call('saveNegotiation')
            ->assertHasNoErrors();

        $staffPage->call('confirmPrice');
        $this->assertSame('negotiation', $trip->fresh()->status);

        $staffPage->call('requestApproval'); // belum ada alasan
        $this->assertSame('none', TripPricing::first()->approval_status);

        $staffPage->set('price.below_min_reason', 'Customer repeat order')
            ->call('saveNegotiation')
            ->call('requestApproval');
        $this->assertSame('pending', TripPricing::first()->approval_status);

        $staffPage->set('approvalNote', 'ok')->call('approve')->assertForbidden();

        $this->page($this->owner, $trip->fresh())
            ->set('approvalNote', 'Disetujui, repeat order')
            ->call('approve');
        $approved = TripPricing::first();
        $this->assertSame('approved', $approved->approval_status);
        $this->assertSame($this->owner->id, $approved->approved_by);

        $this->page($this->staff, $trip->fresh())->call('confirmPrice');
        $this->assertSame('confirmed', $trip->fresh()->status);
    }

    public function test_aturan_blokir_menolak_konfirmasi_meski_diminta_persetujuan(): void
    {
        $this->rule(['block_below_min' => true]);
        $trip = $this->trip();

        $staffPage = $this->negotiating($this->staff, $trip)
            ->set('price.final', '8000000')
            ->set('price.below_min_reason', 'Coba')
            ->call('saveNegotiation')
            ->call('requestApproval');
        $this->assertSame('none', TripPricing::first()->approval_status);

        $this->page($this->owner, $trip->fresh())->set('approvalNote', 'paksa')->call('approve');
        $this->assertSame('none', TripPricing::first()->approval_status);

        $staffPage->call('confirmPrice');
        $this->assertSame('negotiation', $trip->fresh()->status);
    }

    public function test_di_bawah_batas_tanpa_approval_cukup_dengan_alasan(): void
    {
        $this->rule(['require_approval' => false]);
        $trip = $this->trip();

        $page = $this->negotiating($this->staff, $trip)
            ->set('price.final', '8000000')
            ->call('saveNegotiation');

        $page->call('confirmPrice');
        $this->assertSame('negotiation', $trip->fresh()->status); // belum ada alasan

        $page->set('price.below_min_reason', 'Promo akhir tahun')
            ->call('saveNegotiation')
            ->call('confirmPrice');
        $this->assertSame('confirmed', $trip->fresh()->status);
    }

    public function test_mengubah_harga_final_mereset_persetujuan(): void
    {
        $this->rule();
        $trip = $this->trip();

        $staffPage = $this->negotiating($this->staff, $trip)
            ->set('price.final', '8000000')
            ->call('saveNegotiation');

        $this->page($this->owner, $trip->fresh())->set('approvalNote', 'Setuju')->call('approve');
        $this->assertSame('approved', TripPricing::first()->approval_status);

        $staffPage->set('price.final', '8100000')
            ->set('price.change_reason', 'Customer setuju naik sedikit')
            ->call('saveNegotiation');

        $reset = TripPricing::first();
        $this->assertSame('none', $reset->approval_status);
        $this->assertNull($reset->approved_by);
    }

    public function test_harga_di_atas_batas_bisa_langsung_dikonfirmasi(): void
    {
        $this->rule();
        $trip = $this->trip();

        // Final 8.500.000 => margin 25,88% (di atas batas 25%)
        $this->negotiating($this->staff, $trip)
            ->set('price.final', '8500000')
            ->call('saveNegotiation')
            ->call('confirmPrice');

        $this->assertSame('confirmed', $trip->fresh()->status);
    }

    public function test_kembali_ke_estimated_mereset_persetujuan_dan_boleh_nego_ulang(): void
    {
        $rule = $this->rule();
        $trip = $this->trip();

        $page = $this->negotiating($this->staff, $trip)
            ->set('price.final', '8500000')
            ->call('saveNegotiation')
            ->call('backToEstimated');
        $this->assertSame('estimated', $trip->fresh()->status);

        // Biaya berubah, harga rekomendasi dihitung ulang saat negosiasi dimulai lagi
        $bus = $trip->costItems()->where('item_name', 'Bus')->first();
        $page->set("negotiated.{$bus->id}", '4000000')->call('saveNegotiatedCosts');
        $page->call('startNegotiation');

        $this->assertSame('negotiation', $trip->fresh()->status);
        // (4.000.000 + 1.800.000) / 0,7 = 8.285.714 => dibulatkan 8.300.000
        $this->assertEquals(8300000, (float) TripPricing::first()->recommended_price);
        $this->assertEquals(8500000, (float) TripPricing::first()->final_price); // harga final lama tetap tersimpan
    }

    public function test_open_trip_per_pax_memakai_confirmed_pax(): void
    {
        $this->rule();
        $trip = $this->trip(['trip_type' => 'open_trip', 'pax' => 4, 'confirmed_pax' => 6, 'max_capacity' => 10]);

        $page = $this->page($this->staff, $trip)
            ->set('mode', 'per_pax')
            ->call('startNegotiation')
            ->assertHasNoErrors();

        // 6 pax: biaya negosiasi 4.500.000 + (450.000 x 6) = 7.200.000
        // Margin 30% => 10.285.714 => per pax 1.714.286 => dibulatkan 1.700.000 => x 6 = 10.200.000
        $pricing = TripPricing::first();
        $this->assertEquals(10200000, (float) $pricing->recommended_price);

        $page->set('price.final', '1700000')->call('saveNegotiation')->assertHasNoErrors();

        $pricing = $pricing->fresh();
        $this->assertEquals(1700000, (float) $pricing->price_per_pax);
        $this->assertEquals(10200000, (float) $pricing->final_price);
        $this->assertEquals(0, (float) $pricing->customer_discount);
    }

    public function test_mode_per_pax_ditolak_untuk_trip_bukan_open_trip(): void
    {
        $this->rule();
        $trip = $this->trip();

        $this->page($this->staff, $trip)->set('mode', 'per_pax')->call('startNegotiation');

        $this->assertSame('estimated', $trip->fresh()->status);
    }

    public function test_partner_tidak_bisa_membuka_halaman_harga(): void
    {
        $partner = $this->userWithRole('partner');
        $trip = $this->trip(['assigned_partner_id' => $partner->id]);

        $this->actingAs($partner)->get(route('trips.pricing', $trip->id))->assertForbidden();
        $this->actingAs($this->staff)->get(route('trips.pricing', $trip->id))->assertOk();
    }

    public function test_staff_tidak_bisa_mengelola_aturan_harga(): void
    {
        $this->actingAs($this->staff)->get(route('pricing-rules'))->assertForbidden();
        $this->actingAs($this->owner)->get(route('pricing-rules'))->assertOk();
    }
    public function test_peringatan_tarif_nol_muncul_di_halaman_harga(): void
    {
        $this->rule();
        $trip = $this->trip();
        $trip->costItems()->create([
            'item_type' => 'cost_item', 'item_id' => 2, 'quantity' => 1,
            'category' => 'Lainnya', 'item_name' => 'Parkir', 'cost_type' => 'per_trip',
            'cost_behavior' => 'fixed', 'standard_cost' => 0,
        ]);

        $this->page($this->staff, $trip)
            ->assertSee('masih bertarif Rp0')
            ->assertSee('Parkir');
    }

    public function test_final_di_atas_rekomendasi_tidak_tampil_sebagai_diskon_negatif(): void
    {
        $this->rule();
        $trip = $this->trip();

        $this->negotiating($this->staff, $trip)
            ->set('price.final', '9500000')
            ->call('saveNegotiation')
            ->assertSee('Di atas harga rekomendasi')
            ->assertDontSee('Rp -');
    }

    public function test_aturan_harga_ganda_menampilkan_peringatan(): void
    {
        $this->rule();
        $this->rule(['method' => 'cost_plus_markup', 'rate_percent' => 5]);

        Livewire::actingAs($this->owner)
            ->test(\App\Livewire\Pricing\PricingRuleIndex::class)
            ->assertSee('aturan harga yang aktif bersamaan');
    }
}