<?php

namespace Database\Seeders;

use App\Models\PricingRule;
use Illuminate\Database\Seeder;

class PricingRuleSeeder extends Seeder
{
    /** Nilai awal hanya contoh. Owner mengubahnya lewat menu Aturan Harga. */
    public function run(): void
    {
        PricingRule::firstOrCreate(
            ['method' => 'cost_plus_margin', 'status' => 'active'],
            [
                'rate_percent' => 30,
                'rounding_rule' => 'nearest_50000',
                'min_margin_threshold' => 20,
                'block_below_min' => false,
                'require_approval' => true,
            ]
        );
    }
}