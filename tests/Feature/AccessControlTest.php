<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AccessControlTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
    }

    private function makeUser(string $role, bool $finance = false): User
    {
        $user = User::factory()->create([
            'role' => $role,
            'financial_visibility' => $finance,
        ]);
        $user->assignRole($role);
        return $user;
    }

    public function test_staff_tidak_bisa_melihat_halaman_keuangan(): void
    {
        $this->actingAs($this->makeUser('staff'))
            ->get('/keuangan')
            ->assertForbidden();
    }

    public function test_owner_bisa_melihat_halaman_keuangan(): void
    {
        $this->actingAs($this->makeUser('owner', true))
            ->get('/keuangan')
            ->assertOk();
    }

    public function test_partner_dengan_financial_visibility_bisa_melihat_keuangan(): void
    {
        $this->actingAs($this->makeUser('partner', true))
            ->get('/keuangan')
            ->assertOk();
    }
}