<?php

namespace Tests\Feature;

use App\Livewire\Trip\TripForm;
use App\Livewire\Trip\TripShow;
use App\Models\AuditLog;
use App\Models\Destination;
use App\Models\Hotel;
use App\Models\Package;
use App\Models\Trip;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class TripTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
    }

    private function userWithRole(string $role, bool $finance = false): User
    {
        $user = User::factory()->create(['role' => $role, 'financial_visibility' => $finance]);
        $user->assignRole($role);
        return $user;
    }

    /** @return array{0: \App\Models\PackageVersion, 1: Hotel, 2: Destination} */
    private function versionWithItems(): array
    {
        $hotel = Hotel::create([
            'name' => 'Kokoon', 'room_type' => 'Standard', 'standard_rate' => 500000,
            'occupancy' => 2, 'cost_type' => 'per_room', 'status' => 'active',
        ]);
        $dest = Destination::create(['name' => 'Kawah Ijen', 'standard_cost' => 100000, 'status' => 'active']);
        $package = Package::create(['name' => 'Paket Tes', 'trip_type' => 'open_trip', 'status' => 'active']);
        $version = $package->versions()->create([
            'version_no' => 1, 'effective_date' => now()->toDateString(),
            'status' => 'active', 'itinerary' => 'Hari 1 dan 2',
        ]);
        $version->items()->create(['item_type' => 'hotel', 'item_id' => $hotel->id, 'quantity' => 1, 'day_no' => 1]);
        $version->items()->create(['item_type' => 'destination', 'item_id' => $dest->id, 'quantity' => 1, 'day_no' => 1]);

        return [$version, $hotel, $dest];
    }

    private function createTrip(User $user, ?int $versionId = null, array $form = []): Trip
    {
        $component = Livewire::actingAs($user)->test(TripForm::class)
            ->set('form.trip_name', 'Trip Tes')
            ->set('form.customer_name', 'Budi')
            ->set('form.trip_type', 'private')
            ->set('form.start_date', '2026-11-01')
            ->set('form.end_date', '2026-11-02')
            ->set('form.pax', 4);

        foreach ($form as $key => $value) {
            $component->set("form.{$key}", $value);
        }
        if ($versionId) {
            $component->set('packageVersionId', $versionId);
        }

        $component->call('save')->assertHasNoErrors();

        return Trip::latest('id')->first();
    }

    public function test_trip_dari_paket_menyalin_komponen_sebagai_snapshot(): void
    {
        [$version, $hotel] = $this->versionWithItems();

        $trip = $this->createTrip($this->userWithRole('staff'), $version->id);

        $this->assertMatchesRegularExpression('/^SOJ-\d{4}-0001$/', $trip->trip_code);
        $this->assertSame('draft', $trip->status);
        $this->assertSame(2, $trip->duration_days);
        $this->assertSame(2, $trip->costItems()->count());

        $hotelItem = $trip->costItems()->where('item_type', 'hotel')->first();
        $this->assertEquals(2, (float) $hotelItem->calculated_quantity); // 4 pax / 2 per kamar
        $this->assertEquals(1, (float) $hotelItem->duration_qty);        // 2 hari = 1 malam
        $this->assertEquals(500000, (float) $hotelItem->standard_cost);

        // Tarif master berubah, trip lama TIDAK berubah
        $hotel->update(['standard_rate' => 999999]);
        $this->assertEquals(500000, (float) $hotelItem->fresh()->standard_cost);
    }

    public function test_kode_trip_berurutan(): void
    {
        $staff = $this->userWithRole('staff');

        $this->createTrip($staff);
        $second = $this->createTrip($staff);

        $this->assertMatchesRegularExpression('/^SOJ-\d{4}-0002$/', $second->trip_code);
    }

    public function test_ubah_pax_menghitung_ulang_kamar_dan_tercatat(): void
    {
        [$version] = $this->versionWithItems();
        $staff = $this->userWithRole('staff');
        $trip = $this->createTrip($staff, $version->id);

        Livewire::actingAs($staff)
            ->test(TripForm::class, ['trip' => $trip])
            ->set('form.pax', 6)
            ->call('save')
            ->assertHasNoErrors();

        $hotelItem = $trip->costItems()->where('item_type', 'hotel')->first();
        $this->assertEquals(3, (float) $hotelItem->calculated_quantity); // 6 pax = 3 kamar
        $this->assertDatabaseHas('audit_logs', ['entity' => 'trip', 'entity_id' => $trip->id, 'action' => 'updated']);
    }

    public function test_estimated_butuh_komponen(): void
    {
        [, , $dest] = $this->versionWithItems();
        $staff = $this->userWithRole('staff');
        $trip = $this->createTrip($staff);

        $component = Livewire::actingAs($staff)->test(TripShow::class, ['trip' => $trip])
            ->call('changeStatus', 'estimated');
        $this->assertSame('draft', $trip->fresh()->status);

        $component->set('itemForm.item_type', 'destination')
            ->set('itemForm.item_id', $dest->id)
            ->set('itemForm.quantity', 1)
            ->call('addItem')
            ->call('changeStatus', 'estimated');

        $this->assertSame('estimated', $trip->fresh()->status);
    }

    public function test_trip_confirmed_tidak_bisa_diubah(): void
    {
        [, , $dest] = $this->versionWithItems();
        $staff = $this->userWithRole('staff');
        $trip = $this->createTrip($staff);
        $trip->update(['status' => 'confirmed']);

        Livewire::actingAs($staff)->test(TripShow::class, ['trip' => $trip])
            ->set('itemForm.item_type', 'destination')
            ->set('itemForm.item_id', $dest->id)
            ->set('itemForm.quantity', 1)
            ->call('addItem');

        $this->assertSame(0, $trip->costItems()->count());
    }

    public function test_override_kamar_wajib_alasan_dan_tercatat(): void
    {
        [$version] = $this->versionWithItems();
        $staff = $this->userWithRole('staff');
        $trip = $this->createTrip($staff, $version->id);
        $hotelItem = $trip->costItems()->where('item_type', 'hotel')->first();

        $component = Livewire::actingAs($staff)->test(TripShow::class, ['trip' => $trip])
            ->call('startEdit', $hotelItem->id)
            ->set('edit.override_quantity', 3)
            ->call('saveEdit')
            ->assertHasErrors(['edit.override_reason']);

        $this->assertNull($hotelItem->fresh()->override_quantity);

        $component->set('edit.override_reason', 'Tamu minta kamar terpisah')
            ->call('saveEdit')
            ->assertHasNoErrors();

        $fresh = $hotelItem->fresh();
        $this->assertEquals(3, (float) $fresh->override_quantity);
        $this->assertEquals(2, (float) $fresh->calculated_quantity); // nilai hitungan tetap tersimpan
        $this->assertSame($staff->id, $fresh->overridden_by);

        $log = AuditLog::where('entity', 'trip_cost_item')->where('action', 'updated')->latest('id')->first();
        $this->assertSame('Tamu minta kamar terpisah', $log->reason);
        $this->assertEquals(2, $log->new_value['calculated_quantity']);
    }

    public function test_pembatalan_wajib_alasan_dan_hanya_untuk_yang_berizin(): void
    {
        $owner = $this->userWithRole('owner', true);
        $staff = $this->userWithRole('staff');
        $trip = $this->createTrip($owner);

        Livewire::actingAs($staff)->test(TripShow::class, ['trip' => $trip])
            ->call('cancelTrip')
            ->assertForbidden();

        $component = Livewire::actingAs($owner)->test(TripShow::class, ['trip' => $trip])
            ->call('cancelTrip')
            ->assertHasErrors(['cancelReason']);
        $this->assertSame('draft', $trip->fresh()->status);

        $component->set('cancelReason', 'Customer batal')->call('cancelTrip');
        $this->assertSame('cancelled', $trip->fresh()->status);
        $this->assertSame('Customer batal', $trip->fresh()->cancel_reason);
    }

    public function test_partner_hanya_melihat_trip_yang_ditugaskan_dan_tanpa_angka_biaya(): void
    {
        [$version] = $this->versionWithItems();
        $owner = $this->userWithRole('owner', true);
        $assigned = $this->userWithRole('partner');
        $other = $this->userWithRole('partner');

        $trip = $this->createTrip($owner, $version->id, ['assigned_partner_id' => $assigned->id]);

        $this->actingAs($other)->get(route('trips.show', $trip->id))->assertForbidden();

        $this->actingAs($assigned)->get(route('trips.show', $trip->id))
            ->assertOk()
            ->assertDontSee('Total Standard Cost');

        $this->actingAs($owner)->get(route('trips.show', $trip->id))
            ->assertOk()
            ->assertSee('Total Standard Cost');
    }
}