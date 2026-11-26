<?php

namespace Tests\Feature;

use App\Livewire\Package\PackageDetail;
use App\Livewire\Package\PackageIndex;
use App\Livewire\Trip\TripForm;
use App\Models\CostItem;
use App\Models\Destination;
use App\Models\Hotel;
use App\Models\Package;
use App\Models\PackageVersion;
use App\Models\Trip;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class PackageServicePlanTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
        $this->actingAs($this->user('owner', true));
    }

    private function user(string $role, bool $finance = false): User
    {
        $user = User::factory()->create(['role' => $role, 'financial_visibility' => $finance]);
        $user->assignRole($role);

        return $user;
    }

    private function version(string $status = 'draft'): PackageVersion
    {
        $package = Package::create(['name' => 'Private Ijen Baluran', 'trip_type' => 'private',
            'description' => 'Perjalanan untuk keluarga', 'status' => 'active']);

        return $package->versions()->create(['version_no' => 1, 'effective_date' => '2020-01-01',
            'status' => $status, 'duration_days' => 3, 'nights' => 2,
            'itinerary' => 'Titik temu di bandara', 'inclusions' => 'Hotel dan tiket', 'exclusions' => 'Pengeluaran pribadi',
            'itinerary_days' => [['day_no' => 1, 'title' => 'Penjemputan', 'description' => 'Penjemputan dan check-in hotel.'],
                ['day_no' => 3, 'title' => 'Baluran', 'description' => 'Wisata Baluran dan pengantaran pulang.']]]);
    }

    private function hotel(): Hotel
    {
        return Hotel::create(['name' => 'Hotel Keluarga', 'room_type' => 'Twin', 'standard_rate' => 543210,
            'occupancy' => 2, 'cost_type' => 'per_room', 'status' => 'active']);
    }

    private function destination(): Destination
    {
        return Destination::create(['name' => 'Baluran', 'standard_cost' => 12345, 'status' => 'active']);
    }

    public function test_itinerary_and_duration_are_saved_copied_and_audited(): void
    {
        $version = $this->version();
        $component = Livewire::test(PackageDetail::class, ['package' => $version->package])
            ->set('versionForm.itinerary_days.0.title', 'Check-in dan istirahat')
            ->call('saveVersion')->assertHasNoErrors();
        $this->assertSame('Check-in dan istirahat', $version->fresh()->itinerary_days[0]['title']);
        $this->assertDatabaseHas('audit_logs', ['entity' => 'package_version', 'entity_id' => $version->id, 'action' => 'updated']);
        $version->update(['status' => 'active']);
        $component->set('changeReason', 'Penyesuaian layanan')->call('createVersion')->assertHasNoErrors();
        $copy = PackageVersion::where('version_no', 2)->firstOrFail();
        $this->assertEquals(3, $copy->duration_days);
        $this->assertEquals(2, $copy->nights);
        $this->assertSame($version->fresh()->itinerary_days, $copy->itinerary_days);
    }

    public function test_template_prefills_dates_and_snapshots_the_service_plan(): void
    {
        $version = $this->version('active');
        $hotel = $this->hotel();
        $destination = $this->destination();
        $version->items()->create(['item_type' => 'hotel', 'item_id' => $hotel->id, 'quantity' => 1, 'day_no' => 1, 'duration_qty' => 2]);
        $version->items()->create(['item_type' => 'destination', 'item_id' => $destination->id, 'quantity' => 1, 'day_no' => 3]);
        $component = Livewire::test(TripForm::class)->set('packageVersionId', $version->id)
            ->assertSet('form.trip_name', 'Private Ijen Baluran')->assertSet('form.nights', 2)
            ->set('form.start_date', '2030-06-01')->assertSet('form.end_date', '2030-06-03')
            ->set('form.end_date', '2030-06-04')->assertSet('form.end_date', '2030-06-04')
            ->set('form.customer_name', 'Budi')->set('form.pax', 4)->call('save')->assertHasNoErrors();
        $trip = Trip::firstOrFail();
        $this->assertStringContainsString('Hari 3 - Baluran', $trip->itinerary);
        $this->assertStringContainsString('Titik temu di bandara', $trip->itinerary);
        $this->assertEquals(2, $trip->costItems()->where('item_type', 'hotel')->first()->calculated_quantity);
        $this->assertTrue($trip->destinations()->whereKey($destination->id)->exists());
        $before = $trip->itinerary;
        $version->update(['itinerary_days' => [], 'itinerary' => 'DIUBAH', 'inclusions' => 'DIUBAH']);
        $hotel->update(['standard_rate' => 999999]);
        $this->assertSame($before, $trip->fresh()->itinerary);
        $this->assertSame('Hotel dan tiket', $trip->fresh()->inclusions);
        $this->assertEquals(543210, $trip->costItems()->where('item_type', 'hotel')->first()->standard_cost);
        $this->assertDatabaseCount('trip_pricing', 0);
    }

    public function test_automatic_quantities_are_not_manual_inputs_and_are_normalized_on_server(): void
    {
        $version = $this->version();
        $hotel = $this->hotel();
        $component = Livewire::test(PackageDetail::class, ['package' => $version->package])
            ->set('itemForm.item_type', 'hotel')->set('itemForm.item_id', $hotel->id)
            ->assertSee('orang/unit')->assertDontSee('id="service-quantity"', false)
            ->set('itemForm.quantity', 9)->set('itemForm.duration_qty', 2)->call('addItem')->assertHasNoErrors();
        $this->assertEquals(1, $version->items()->first()->quantity);
        $destination = $this->destination();
        $component->set('itemForm.item_type', 'destination')->set('itemForm.item_id', $destination->id)
            ->assertSee('Berlaku untuk seluruh peserta trip')->assertDontSee('id="service-duration"', false)
            ->set('itemForm.quantity', 7)->set('itemForm.duration_qty', 9)->call('addItem')->assertHasNoErrors();
        $row = $version->items()->where('item_type', 'destination')->first();
        $this->assertEquals(1, $row->quantity);
        $this->assertNull($row->duration_qty);
    }

    public function test_manual_fixed_quantity_is_preserved(): void
    {
        $version = $this->version();
        $cost = CostItem::create(['name' => 'Parkir', 'category' => 'parking', 'standard_cost' => 10000, 'cost_type' => 'per_trip', 'status' => 'active']);
        Livewire::test(PackageDetail::class, ['package' => $version->package])
            ->set('itemForm.item_type', 'cost_item')->set('itemForm.item_id', $cost->id)
            ->assertSee('id="service-quantity"', false)->set('itemForm.quantity', 3)
            ->call('addItem')->assertHasNoErrors();
        $this->assertEquals(3, $version->items()->first()->quantity);
    }

    public function test_invalid_nights_duplicate_days_and_days_outside_plan_are_rejected(): void
    {
        $version = $this->version();
        $component = Livewire::test(PackageDetail::class, ['package' => $version->package])
            ->set('versionForm.nights', 4)->call('saveVersion')->assertHasErrors('versionForm.nights');
        $component->set('versionForm.nights', 2)->set('versionForm.itinerary_days.1.day_no', 1)
            ->call('saveVersion')->assertHasErrors('versionForm.itinerary_days.1.day_no');
        $component->set('versionForm.itinerary_days.1.day_no', 4)->call('saveVersion')
            ->assertHasErrors('versionForm.itinerary_days.1.day_no');
        $this->assertEquals(2, $version->fresh()->nights);
    }

    public function test_services_cannot_extend_past_default_trip_duration(): void
    {
        $version = $this->version();
        $hotel = $this->hotel();
        Livewire::test(PackageDetail::class, ['package' => $version->package])
            ->set('itemForm.item_type', 'hotel')->set('itemForm.item_id', $hotel->id)
            ->set('itemForm.day_no', 3)->set('itemForm.duration_qty', 1)
            ->call('addItem')->assertHasErrors('itemForm.day_no');
        $this->assertSame(0, $version->items()->count());
    }

    public function test_trip_dates_cannot_truncate_itinerary_even_when_cost_items_fit(): void
    {
        $version = $this->version('active');
        $destination = $this->destination();
        $version->items()->create(['item_type' => 'destination', 'item_id' => $destination->id, 'quantity' => 1, 'day_no' => 1]);
        Livewire::test(TripForm::class)->set('packageVersionId', $version->id)
            ->set('form.customer_name', 'Budi')->set('form.start_date', '2030-01-01')
            ->set('form.end_date', '2030-01-02')->call('save')->assertHasErrors('packageVersionId');
        $this->assertDatabaseCount('trips', 0);
    }

    public function test_cost_visibility_and_template_management_follow_existing_permissions(): void
    {
        $version = $this->version();
        $hotel = $this->hotel();
        $version->items()->create(['item_type' => 'hotel', 'item_id' => $hotel->id, 'quantity' => 1, 'day_no' => 1, 'duration_qty' => 2]);
        Livewire::actingAs($this->user('staff'))->test(PackageDetail::class, ['package' => $version->package])
            ->assertSee('Rencana perjalanan')->assertSee('Hotel Keluarga')->assertSee('Tarif referensi:')
            ->call('addItineraryDay')->assertForbidden();
        Livewire::actingAs($this->user('partner'))->test(PackageDetail::class, ['package' => $version->package])
            ->assertSee('Rencana perjalanan')->assertSee('Hotel Keluarga')->assertDontSee('Tarif referensi:')
            ->call('saveVersion')->assertForbidden();
        Livewire::test(PackageIndex::class)->assertSee('3 hari / 2 malam')->assertSee('Template Paket Wisata');
    }

    public function test_active_template_plan_cannot_be_changed_and_legacy_text_is_preserved(): void
    {
        $version = $this->version('active');
        Livewire::test(PackageDetail::class, ['package' => $version->package])->call('addItineraryDay')
            ->assertSet('versionForm.itinerary_days', $version->itinerary_days)
            ->set('versionForm.duration_days', 5)->call('saveVersion');
        $this->assertSame(3, $version->fresh()->duration_days);
        $version->update(['duration_days' => null, 'nights' => null, 'itinerary_days' => null]);
        $this->assertSame('Titik temu di bandara', $version->fresh()->itineraryForTrip());
    }
}
