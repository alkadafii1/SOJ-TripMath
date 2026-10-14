<?php

namespace Tests\Feature;

use App\Livewire\Master\HotelIndex;
use App\Livewire\Master\VendorIndex;
use App\Models\AuditLog;
use App\Models\Hotel;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class MasterDataTest extends TestCase
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

    public function test_staff_hanya_bisa_melihat_tidak_bisa_menambah(): void
    {
        Livewire::actingAs($this->userWithRole('staff'))
            ->test(VendorIndex::class)
            ->assertOk()
            ->call('create')
            ->assertForbidden();
    }

    public function test_owner_menambah_vendor_dan_tercatat_di_audit_log(): void
    {
        $owner = $this->userWithRole('owner');

        Livewire::actingAs($owner)
            ->test(VendorIndex::class)
            ->call('create')
            ->set('form.name', 'Hotel Kokoon')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('vendors', ['name' => 'Hotel Kokoon']);
        $this->assertDatabaseHas('audit_logs', [
            'entity' => 'vendor',
            'action' => 'created',
            'user_id' => $owner->id,
        ]);
    }

    public function test_ubah_tarif_wajib_alasan_dan_tercatat(): void
    {
        $owner = $this->userWithRole('owner');
        $hotel = Hotel::create([
            'name' => 'Kokoon', 'room_type' => 'Standard',
            'standard_rate' => 500000, 'occupancy' => 2,
            'cost_type' => 'per_room', 'status' => 'active',
        ]);

        $component = Livewire::actingAs($owner)
            ->test(HotelIndex::class)
            ->call('edit', $hotel->id)
            ->set('form.standard_rate', 550000)
            ->call('save')
            ->assertHasErrors(['reason']);

        $this->assertEquals(500000, (float) $hotel->fresh()->standard_rate);

        $component->set('reason', 'Kenaikan tarif 2026')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertEquals(550000, (float) $hotel->fresh()->standard_rate);

        $log = AuditLog::where('action', 'updated')->latest('id')->first();
        $this->assertSame('Kenaikan tarif 2026', $log->reason);
        $this->assertEquals(500000, (float) $log->old_value['standard_rate']);
        $this->assertEquals(550000, (float) $log->new_value['standard_rate']);
    }
}