<?php

namespace Database\Seeders;

use App\Models\PricingRule;
use Illuminate\Database\Seeder;

class PricingRuleSeeder extends Seeder
{
    /** Nilai awal hanya contoh. Owner mengubahnya lewat menu Aturan Harga. */
    public function run(): void
    {
        if (! app()->environment(['local', 'testing']) || PricingRule::query()->exists()) {
            return;
        }

        $rules = [
            // 1. Rule Default (Markup 20%)
            [
                'method' => 'cost_plus_markup',
                'status' => 'active',
                'rate_percent' => 20,
                'rounding_rule' => 'exact',
                'min_margin_threshold' => 10,
                'block_below_min' => false,
                'require_approval' => true,
            ],
            
            // 2. Rule Premium (Margin 30%)
            [
                'method' => 'cost_plus_margin',
                'status' => 'active',
                'rate_percent' => 30,
                'rounding_rule' => 'nearest_50000',
                'min_margin_threshold' => 20,
                'block_below_min' => true,
                'require_approval' => true,
            ],

            // 3. Rule Rombongan Besar (Markup 15%)
            [
                'method' => 'cost_plus_markup',
                'status' => 'active',
                'rate_percent' => 15,
                'rounding_rule' => 'nearest_10000',
                'min_margin_threshold' => 8,
                'block_below_min' => false,
                'require_approval' => false,
            ],

            // 4. Rule Peak Season / High Demand (Margin 35%)
            [
                'method' => 'cost_plus_margin',
                'status' => 'active',
                'rate_percent' => 35,
                'rounding_rule' => 'nearest_50000',
                'min_margin_threshold' => 25,
                'block_below_min' => true,
                'require_approval' => true,
            ],

            // 5. Rule Repeat Customer / Diskon (Markup 10%)
            [
                'method' => 'cost_plus_markup',
                'status' => 'active',
                'rate_percent' => 10,
                'rounding_rule' => 'exact',
                'min_margin_threshold' => 5,
                'block_below_min' => false,
                'require_approval' => false,
            ],
        ];

        foreach ($rules as $index => $rule) {
            // Only the demo default is active; never replace existing business rules.
            $rule['status'] = $index === 0 ? 'active' : 'inactive';
            PricingRule::create($rule);
        }
    }
}