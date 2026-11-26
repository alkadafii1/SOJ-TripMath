<?php

namespace Tests\Feature;

use App\Livewire\Concerns\LoadsActiveMasters;
use App\Models\Hotel;
use App\Models\Trip;
use App\Models\User;
use App\Services\DashboardService;
use App\Services\TripCostService;
use App\Services\TripItemFactory;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class PerformanceQueryTest extends TestCase
{
    use RefreshDatabase;

    private function user(string $role): User
    {
        $user = User::factory()->create(['role' => $role]);
        $user->assignRole($role);
        $user->getAllPermissions();

        return $user;
    }

    private function trip(User $owner, array $attributes = []): Trip
    {
        return Trip::create($attributes + [
            'trip_code' => 'PERF-'.uniqid(), 'trip_name' => 'Query budget',
            'customer_name' => 'Test', 'trip_type' => 'private',
            'start_date' => today(), 'end_date' => today()->addDay(),
            'duration_days' => 2, 'nights' => 1, 'pax' => 4,
            'status' => 'draft', 'created_by' => $owner->id,
        ]);
    }

    public function test_action_counts_use_one_round_trip_and_keep_visibility_scopes(): void
    {
        $this->seed(RolePermissionSeeder::class);
        $owner = $this->user('owner');
        $partner = $this->user('partner');
        $service = app(DashboardService::class);
        $this->trip($owner, ['status' => 'completed']);
        $visible = $this->trip($owner, ['status' => 'completed', 'assigned_partner_id' => $partner->id]);

        DB::enableQueryLog();
        try {
            $cards = $service->actions($owner, ['approval', 'actual_cost', 'reconcile']);
            $queries = collect(DB::getQueryLog())->filter(fn ($q) => str_contains($q['query'], 'trips'));
            $this->assertCount(2, $queries); // One combined count and one nonempty example list.
            $this->assertSame(['reconcile'], array_column($cards, 'key'));
            $this->assertSame(2, $cards[0]['count']);
            $sql = $queries->first()['query'];
            $this->assertStringNotContainsString('quote_valid_until', $sql);
            $this->assertStringNotContainsString('min_pax', $sql);

            $cards = $service->actions($partner, ['reconcile']);
            $this->assertSame(1, $cards[0]['count']);
            $this->assertSame($visible->id, $cards[0]['trips'][0]['id']);
            DB::flushQueryLog();
            $this->assertSame([], $service->actions($owner, []));
            $this->assertCount(0, DB::getQueryLog());
        } finally {
            DB::disableQueryLog();
            DB::flushQueryLog();
        }
    }

    public function test_cost_summary_reuses_loaded_items_and_master_rates_stay_request_local(): void
    {
        $owner = User::factory()->create();
        $trip = $this->trip($owner);
        $hotel = Hotel::create(['name' => 'Twin', 'room_type' => 'Twin', 'standard_rate' => 200000,
            'occupancy' => 2, 'cost_type' => 'per_room', 'status' => 'active']);
        $trip->costItems()->create(TripItemFactory::make('hotel', $hotel, $trip));
        $items = $trip->costItems()->active()->get();
        $costs = app(TripCostService::class);
        $expected = $costs->summary($trip);
        $loader = fn () => new class
        {
            use LoadsActiveMasters;

            public function hotels()
            {
                return $this->activeMasters('hotel');
            }
        };
        DB::enableQueryLog();
        try {
            $this->assertSame($expected, $costs->summary($trip, items: $items));
            $this->assertCount(0, DB::getQueryLog());
            $costs->recalculate($trip);
            $reads = collect(DB::getQueryLog())->filter(fn ($q) => str_starts_with(strtolower($q['query']), 'select')
                && str_contains($q['query'], 'trip_cost_items'));
            $this->assertCount(1, $reads);

            DB::flushQueryLog();
            $request = $loader();
            $this->assertSame($request->hotels(), $request->hotels());
            $this->assertCount(1, DB::getQueryLog());
            $hotel->update(['standard_rate' => 300000]);
            $this->assertEquals(200000, $request->hotels()->first()->standard_rate);
            $this->assertEquals(300000, $loader()->hotels()->first()->standard_rate);
        } finally {
            DB::disableQueryLog();
            DB::flushQueryLog();
        }
    }
}
