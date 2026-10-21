<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SidebarTest extends TestCase
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

    public function test_owner_melihat_semua_menu(): void
    {
        $this->actingAs($this->userWithRole('owner', true))
            ->get('/dashboard')
            ->assertOk()
            ->assertSee('Trip')
            ->assertSee('Paket')
            ->assertSee('Data Master')
            ->assertSee('Aturan Harga');
    }

    public function test_staff_tidak_melihat_menu_aturan_harga(): void
    {
        $this->actingAs($this->userWithRole('staff'))
            ->get('/dashboard')
            ->assertOk()
            ->assertSee('Data Master')
            ->assertDontSee('Aturan Harga');
    }

    public function test_menu_yang_sedang_dibuka_ditandai(): void
    {
        $this->actingAs($this->userWithRole('owner', true))
            ->get('/trips')
            ->assertOk()
            ->assertSee('aria-current="page"', false);
    }

    public function test_hanya_satu_menu_yang_aktif_di_halaman_paket(): void
    {
    $html = $this->actingAs($this->userWithRole('owner', true))
        ->get(route('packages'))
        ->assertOk()
        ->getContent();

    $this->assertSame(1, substr_count($html, 'aria-current="page"'));
    }
}