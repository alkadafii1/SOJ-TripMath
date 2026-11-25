<?php

namespace Tests\Feature;

use App\Livewire\Package\PackageDetail;
use App\Livewire\Trip\TripForm;
use App\Models\Destination;
use App\Models\Hotel;
use App\Models\Package;
use App\Models\Trip;
use App\Models\User;
use App\Services\TripCostService;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class TemplateBookingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
        $owner = User::factory()->create(['role' => 'owner', 'financial_visibility' => true]);
        $owner->assignRole('owner');
        $this->actingAs($owner);
    }

    private function template(): array
    {
        $hotel = Hotel::create(['name' => 'Hotel Template', 'room_type' => 'Twin', 'standard_rate' => 500000,
            'occupancy' => 2, 'cost_type' => 'per_room', 'status' => 'active']);
        $package = Package::create(['name' => 'Template Customer', 'trip_type' => 'private', 'status' => 'active']);
        $version = $package->versions()->create(['version_no' => 1, 'status' => 'active',
            'effective_date' => '2020-01-01', 'itinerary' => 'Hari 1 dan 2', 'inclusions' => 'Hotel', 'exclusions' => 'Tip']);
        $version->items()->create(['item_type' => 'hotel', 'item_id' => $hotel->id, 'quantity' => 1,
            'day_no' => 1, 'duration_qty' => 1]);
        $version->items()->create(['item_type' => 'hotel', 'item_id' => $hotel->id, 'quantity' => 1,
            'day_no' => 2, 'duration_qty' => 1]);

        return [$package, $version, $hotel];
    }

    private function booking(int $versionId)
    {
        return Livewire::test(TripForm::class)
            ->set('packageVersionId', $versionId)
            ->set('form.customer_name', 'Customer Baru')
            ->set('form.start_date', '2030-01-01')
            ->set('form.end_date', '2030-01-03')
            ->set('form.pax', 4);
    }

    public function test_booking_copies_template_and_fixed_service_duration_without_doubling(): void
    {
        [$package, $version, $hotel] = $this->template();
        $this->get(route('trips.create', ['template' => $version->id]))->assertOk()->assertSee('Template Customer');
        Livewire::test(PackageDetail::class, ['package' => $package])->assertSee('Gunakan template untuk trip customer');
        $this->booking($version->id)->assertSet('form.trip_name', $package->name)->call('save')->assertHasNoErrors();
        $trip = Trip::firstOrFail();
        $this->assertSame('draft', $trip->status);
        $this->assertSame($version->itinerary, $trip->itinerary);
        $this->assertSame('Hotel', $trip->inclusions);
        $this->assertSame('Tip', $trip->exclusions);
        $this->assertEquals([1, 2], $trip->costItems()->pluck('day_no')->all());
        $this->assertEquals(2000000, app(TripCostService::class)->summary($trip)['total']);
        Livewire::test(TripForm::class, ['trip' => $trip])->set('form.end_date', '2030-01-04')
            ->call('save')->assertHasNoErrors();
        $this->assertEquals([1, 1], $trip->costItems()->pluck('duration_qty')->all());
        $hotel->update(['standard_rate' => 999999]);
        $this->assertEquals(500000, $trip->costItems()->first()->standard_cost);
        $this->assertEquals(1, $version->items()->first()->duration_qty);
    }

    public function test_unavailable_master_rolls_back_the_whole_booking(): void
    {
        [, $version, $hotel] = $this->template();
        $hotel->delete();
        $this->booking($version->id)->call('save')->assertHasErrors('packageVersionId');
        $this->assertSame(0, Trip::count());
        $this->assertDatabaseCount('trip_cost_items', 0);
    }

    public function test_expired_rate_and_short_trip_are_rejected(): void
    {
        [, $version, $hotel] = $this->template();
        $hotel->update(['valid_until' => '2029-12-31']);
        $this->booking($version->id)->call('save')->assertHasErrors('packageVersionId');
        $hotel->update(['valid_until' => null]);
        $this->booking($version->id)->set('form.end_date', '2030-01-01')
            ->call('save')->assertHasErrors('packageVersionId');
        $this->assertSame(0, Trip::count());
    }

    public function test_inactive_package_and_future_version_are_rejected(): void
    {
        [$package, $version] = $this->template();
        $version->update(['effective_date' => '2031-01-01']);
        $this->booking($version->id)->call('save')->assertHasErrors('packageVersionId');
        $version->update(['effective_date' => '2020-01-01']);
        $package->update(['status' => 'inactive']);
        $this->booking($version->id)->set('form.trip_name', 'Manual')->call('save')->assertHasErrors('packageVersionId');
        $this->assertSame(0, Trip::count());
    }

    public function test_draft_component_can_be_edited_published_and_then_locked(): void
    {
        [$package, $version] = $this->template();
        $version->update(['status' => 'draft']);
        $item = $version->items()->first();
        $component = Livewire::test(PackageDetail::class, ['package' => $package])
            ->call('editItem', $item->id)->set('itemForm.duration_qty', 2)->call('addItem')->assertHasNoErrors();
        $this->assertEquals(2, $item->fresh()->duration_qty);
        $this->assertSame(2, $version->items()->count());
        $component->call('publish')->assertHasNoErrors();
        $this->assertSame('active', $version->fresh()->status);
        $component->call('editItem', $item->id)->assertSet('editingItemId', null);
        $this->assertEquals(2, $item->fresh()->duration_qty);
    }

    public function test_publish_rejects_inactive_components(): void
    {
        [$package, $version, $hotel] = $this->template();
        $version->update(['status' => 'draft']);
        $hotel->update(['status' => 'inactive']);
        Livewire::test(PackageDetail::class, ['package' => $package])->call('publish')->assertHasErrors('packageVersionId');
        $this->assertSame('draft', $version->fresh()->status);
    }

    public function test_per_person_quantity_cannot_be_silently_ignored(): void
    {
        [, $version] = $this->template();
        $destination = Destination::create(['name' => 'Tiket', 'standard_cost' => 10000, 'status' => 'active']);
        $version->items()->create(['item_type' => 'destination', 'item_id' => $destination->id,
            'quantity' => 3, 'day_no' => 1]);
        $this->booking($version->id)->call('save')->assertHasErrors('packageVersionId');
        $this->assertSame(0, Trip::count());
    }
}
