<?php

namespace Tests\Feature;

use App\Livewire\Package\PackageDetail;
use App\Models\Hotel;
use App\Models\Package;
use App\Models\PackageVersionItem;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class PackageTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
    }

    private function userWithRole(string $role): User
    {
        $user = User::factory()->create(['role' => $role]);
        $user->assignRole($role);
        return $user;
    }

    private function package(): Package
    {
        return Package::create(['name' => 'Paket Tes', 'trip_type' => 'private', 'status' => 'active']);
    }

    private function hotel(): Hotel
    {
        return Hotel::create([
            'name' => 'Kokoon', 'room_type' => 'Standard', 'standard_rate' => 500000,
            'occupancy' => 2, 'cost_type' => 'per_room', 'status' => 'active',
        ]);
    }

    /** Membuat versi 1 berisi satu hotel, lalu dipublikasikan. */
    private function publishedV1(User $owner, Package $package, Hotel $hotel)
    {
        return Livewire::actingAs($owner)
            ->test(PackageDetail::class, ['package' => $package])
            ->call('createVersion')
            ->set('itemForm.item_type', 'hotel')
            ->set('itemForm.item_id', $hotel->id)
            ->set('itemForm.quantity', 1)
            ->set('itemForm.day_no', 1)
            ->call('addItem')
            ->call('publish');
    }

    public function test_staff_tidak_bisa_membuat_versi(): void
    {
        Livewire::actingAs($this->userWithRole('staff'))
            ->test(PackageDetail::class, ['package' => $this->package()])
            ->call('createVersion')
            ->assertForbidden();
    }

    public function test_versi_yang_dipublikasikan_terkunci(): void
    {
        $owner = $this->userWithRole('owner');
        $package = $this->package();
        $hotel = $this->hotel();

        $component = $this->publishedV1($owner, $package, $hotel);

        $this->assertDatabaseHas('package_versions', [
            'package_id' => $package->id, 'version_no' => 1, 'status' => 'active',
        ]);
        $this->assertDatabaseCount('package_version_items', 1);

        // ubah isi versi aktif harus ditolak
        $component->set('versionForm.itinerary', 'DIUBAH')->call('saveVersion');
        $this->assertDatabaseMissing('package_versions', ['itinerary' => 'DIUBAH']);

        // tambah komponen ke versi aktif harus ditolak
        $component->set('itemForm.item_type', 'hotel')
            ->set('itemForm.item_id', $hotel->id)
            ->call('addItem');
        $this->assertDatabaseCount('package_version_items', 1);
    }

    public function test_versi_baru_menyalin_komponen_dan_wajib_alasan(): void
    {
        $owner = $this->userWithRole('owner');
        $package = $this->package();

        $component = $this->publishedV1($owner, $package, $this->hotel());

        $component->call('createVersion')->assertHasErrors(['changeReason']);

        $component->set('changeReason', 'Ganti hotel')
            ->call('createVersion')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('package_versions', [
            'version_no' => 2, 'status' => 'draft', 'change_log' => 'Ganti hotel',
        ]);
        $this->assertEquals(2, PackageVersionItem::count()); // komponen v1 tersalin ke v2

        $component->call('publish');

        $this->assertDatabaseHas('package_versions', ['version_no' => 1, 'status' => 'archived']);
        $this->assertDatabaseHas('package_versions', ['version_no' => 2, 'status' => 'active']);
    }

    public function test_publish_tanpa_komponen_ditolak(): void
    {
        $owner = $this->userWithRole('owner');
        $package = $this->package();

        Livewire::actingAs($owner)
            ->test(PackageDetail::class, ['package' => $package])
            ->call('createVersion')
            ->call('publish');

        $this->assertDatabaseHas('package_versions', ['version_no' => 1, 'status' => 'draft']);
    }
}