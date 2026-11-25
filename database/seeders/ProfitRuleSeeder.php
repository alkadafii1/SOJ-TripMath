<?php

namespace Database\Seeders;

use App\Models\ProfitRule;
use App\Services\ProfitRuleService;
use Illuminate\Database\Seeder;

class ProfitRuleSeeder extends Seeder
{
    public function run(): void
    {
        // An existing (even inactive) configuration is an explicit owner decision.
        if (ProfitRule::query()->exists()) {
            return;
        }

        app(ProfitRuleService::class)->createSet([
            'marketing_percent' => '5',
            'marketing_base' => 'revenue',
            'owner_percent' => '60',
            'partner_percent' => '40',
            'effective_from' => today()->toDateString(),
        ], 'Konfigurasi awal sesuai spesifikasi NASHIR.ID: marketing 5%, Owner 60%, Partner 40%.');
    }
}
