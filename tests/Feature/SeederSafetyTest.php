<?php

namespace Tests\Feature;

use App\Models\Hotel;
use App\Models\Package;
use App\Models\PricingRule;
use App\Models\ProfitRule;
use App\Models\User;
use App\Services\ProfitRuleService;
use App\Support\Options;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\ProfitRuleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class SeederSafetyTest extends TestCase
{
    use RefreshDatabase;

    public function test_repeated_seeding_preserves_rates_passwords_rules_and_deleted_records(): void
    {
        $this->seed(DatabaseSeeder::class);
        $tables = ['users', 'vendors', 'hotels', 'vehicles', 'destinations', 'activities', 'meals', 'cost_items', 'packages', 'package_versions', 'package_version_items', 'pricing_rules', 'profit_rules'];
        $counts = collect($tables)->mapWithKeys(fn ($table) => [$table => DB::table($table)->count()]);
        $owner = User::where('email', 'owner@soj.test')->firstOrFail();
        $owner->update(['password' => 'changed-owner-password', 'financial_visibility' => false]);
        $password = $owner->fresh()->password;
        $hotel = Hotel::firstOrFail();
        $hotel->update(['standard_rate' => 123456]);
        $hotel->delete();
        $rule = PricingRule::firstOrFail();
        $rule->update(['rate_percent' => 27]);
        $this->seed(DatabaseSeeder::class);
        foreach ($counts as $table => $count) {
            $this->assertSame($count, DB::table($table)->count(), $table);
        }
        $this->assertSame($password, $owner->fresh()->password);
        $this->assertFalse($owner->fresh()->financial_visibility);
        $this->assertEquals(123456, Hotel::withTrashed()->findOrFail($hotel->id)->standard_rate);
        $this->assertSoftDeleted('hotels', ['id' => $hotel->id]);
        $this->assertEquals(27, $rule->fresh()->rate_percent);
        $this->assertSame(1, PricingRule::where('status', 'active')->count());
    }

    public function test_fresh_seed_has_complete_package_and_valid_cost_types(): void
    {
        $this->seed(DatabaseSeeder::class);
        $version = Package::where('name', 'Open Trip 2D1N - Opsi B')->firstOrFail()->versions()->firstOrFail();
        $this->assertSame(10, $version->items()->count());
        $this->assertTrue($version->items()->where('item_type', 'hotel')->exists());
        $this->assertTrue($version->items()->where('item_type', 'vehicle')->exists());
        foreach (['activities', 'vehicles', 'cost_items'] as $table) {
            $this->assertSame(0, DB::table($table)->whereNotIn('cost_type', array_keys(Options::COST_TYPES))->count(), $table);
        }
        $this->assertEquals(10, DB::table('cost_items')->where('name', 'Handling Fee Vendor (10%)')->value('percentage'));
        $this->assertDatabaseHas('cost_items', ['name' => 'Handling Fee Vendor (10%)', 'cost_type' => 'percentage', 'cost_behavior' => 'variable']);
        $this->assertNotNull(Hotel::where('name', 'Hotel Kokoon')->firstOrFail()->vendor_id);
    }

    public function test_profit_defaults_are_configurable_and_never_overwrite_existing_rules(): void
    {
        $this->seed(ProfitRuleSeeder::class);
        $rules = app(ProfitRuleService::class)->resolve();
        $this->assertEquals(5, $rules['marketing']['percent']);
        $this->assertSame('revenue', $rules['marketing']['base']);
        $this->assertEquals(60, $rules['shares']['owner']['percent']);
        $this->assertEquals(40, $rules['shares']['partner']['percent']);
        ProfitRule::query()->update(['status' => 'inactive']);
        $this->seed(ProfitRuleSeeder::class);
        $this->assertSame(3, ProfitRule::count());
        $this->assertNull(app(ProfitRuleService::class)->resolve());
    }

    public function test_production_seeding_does_not_create_demo_accounts_or_rates(): void
    {
        $original = app()->environment();
        app()->instance('env', 'production');
        try {
            // Invoke the seeder directly: this test simulates the production environment,
            // not the CLI's interactive production confirmation.
            app(DatabaseSeeder::class)->__invoke();
            $this->assertSame(0, User::count());
            foreach (['hotels', 'vehicles', 'packages', 'pricing_rules', 'vendors'] as $table) {
                $this->assertSame(0, DB::table($table)->count(), $table);
            }
            $this->assertSame(3, ProfitRule::count());
        } finally {
            app()->instance('env', $original);
        }
    }
}
