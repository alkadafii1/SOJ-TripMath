<?php

namespace Tests\Feature;

use App\Livewire\Audit\AuditIndex;
use App\Livewire\Trip\PricingPage;
use App\Models\AuditLog;
use App\Models\Trip;
use App\Models\User;
use App\Services\AuditLogger;
use App\Support\ReportPeriod;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class SprintNineSecurityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
    }

    private function user(string $role, bool $finance = false): User
    {
        $user = User::factory()->create(['role' => $role, 'financial_visibility' => $finance]);
        $user->assignRole($role);

        return $user;
    }

    public function test_audit_requires_login_and_explicit_permission(): void
    {
        $this->get(route('audit'))->assertRedirect(route('login'));
        foreach (['staff', 'partner'] as $role) {
            $user = $this->user($role, true);
            $this->actingAs($user)->get(route('audit'))->assertForbidden();
            Livewire::actingAs($user)->test(AuditIndex::class)->assertForbidden();
        }
        $this->actingAs($this->user('owner'))->get(route('audit'))->assertOk();
    }

    public function test_audit_filters_and_escapes_change_values(): void
    {
        $owner = $this->user('owner');
        $this->actingAs($owner);
        AuditLogger::record('trip', 7, 'updated', ['status' => 'draft'], ['note' => '<script>alert(1)</script>'], 'Revisi uji');
        AuditLogger::record('hotel', 8, 'created', null, ['name' => 'Hotel pembanding']);
        Livewire::test(AuditIndex::class)
            ->set('entity', 'trip')->set('actor', (string) $owner->id)->set('recordId', '7')
            ->set('action', 'updated')->set('from', today()->toDateString())->set('to', today()->toDateString())
            ->assertSee('Revisi uji')->assertSee('draft')
            ->assertDontSee('Hotel pembanding')->assertDontSee('<script>alert(1)</script>', false)
            ->set('from', '2026-02-30')->assertSee('Tidak ada riwayat untuk filter ini.');
    }

    public function test_audit_permission_is_checked_again_after_mount(): void
    {
        $owner = $this->user('owner');
        $page = Livewire::actingAs($owner)->test(AuditIndex::class);
        $owner->syncRoles(['staff']);
        $page->set('entity', 'trip')->assertForbidden();
    }

    public function test_audit_records_cannot_be_updated_or_deleted_through_model(): void
    {
        $log = AuditLogger::record('trip', 1, 'created');
        $this->assertFalse($log->update(['action' => 'tampered']));
        $this->assertFalse($log->delete());
        $this->assertSame('created', $log->fresh()->action);
    }

    public function test_partner_with_financial_visibility_still_cannot_open_pricing(): void
    {
        $partner = $this->user('partner', true);
        $trip = Trip::create([
            'trip_code' => 'SOJ-SEC-001', 'trip_name' => 'Security trip', 'customer_name' => 'Customer',
            'trip_type' => 'private', 'start_date' => '2026-11-01', 'end_date' => '2026-11-02',
            'duration_days' => 2, 'pax' => 4, 'status' => 'draft',
            'created_by' => $partner->id, 'assigned_partner_id' => $partner->id,
        ]);
        $this->actingAs($partner)->get(route('trips.pricing', $trip))->assertForbidden();
        Livewire::actingAs($partner)->test(PricingPage::class, ['trip' => $trip])->assertForbidden();
    }

    public function test_packages_redirect_guests_to_login(): void
    {
        $this->get(route('packages'))->assertRedirect(route('login'));
    }

    public function test_custom_period_rejects_impossible_and_relative_dates(): void
    {
        foreach (['2026-02-30', 'tomorrow', '2026-2-01', '2026-01-01 12:00:00'] as $date) {
            [$period, $error] = ReportPeriod::resolve('custom', $date, '2026-12-31');
            $this->assertNotNull($error);
            $this->assertSame('month', $period->preset);
        }
        [$period, $error] = ReportPeriod::resolve('custom', '2028-02-29', '2028-02-29');
        $this->assertNull($error);
        $this->assertSame('custom', $period->preset);
    }
}
