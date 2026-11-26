<?php

namespace Tests\Feature;

use App\Livewire\Report\ReportIndex;
use App\Models\User;
use App\Services\ReportService;
use Database\Seeders\RolePermissionSeeder;
use DOMDocument;
use DOMXPath;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ReportNavigationTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
        $this->owner = User::factory()->create(['role' => 'owner', 'financial_visibility' => true]);
        $this->owner->assignRole('owner');
    }

    public static function reportCases(): array
    {
        return [
            'profit trip' => ['trip-profit', 'Actual Profit'],
            'vendor saving' => ['vendor-saving', 'Negotiated Cost'],
            'customer discount' => ['customer-discount', 'Harga rekomendasi'],
            'profit sharing' => ['profit-sharing', 'Distributable'],
            'cost variance' => ['cost-variance', 'Selisih vs negosiasi'],
            'trend' => ['trend', 'Bulan'],
        ];
    }

    #[DataProvider('reportCases')]
    public function test_sidebar_destination_displays_the_selected_report(string $key, string $column): void
    {
        $response = $this->actingAs($this->owner)
            ->get(route('reports', ['report' => $key]), ['X-Livewire-Navigate' => 'true'])
            ->assertOk()
            ->assertSee(ReportService::REPORTS[$key])
            ->assertSee($column)
            ->assertSee(route('reports.export', ['report' => $key]), false);

        $xpath = $this->xpath($response->getContent());
        $active = $xpath->query('//aside//a[@aria-current="page"]');
        $this->assertCount(1, $active);
        $this->assertSame(route('reports', ['report' => $key]), $active->item(0)->getAttribute('href'));

        foreach (array_keys(ReportService::REPORTS) as $report) {
            $links = $xpath->query('//aside//a[@href="'.route('reports', ['report' => $report]).'"]');
            $this->assertCount(1, $links);
            $this->assertTrue($links->item(0)->hasAttribute('wire:navigate'));
        }

        $this->assertCount(0, $xpath->query('//main//button[contains(@*[name()="wire:click"], "report")]'));
        $this->assertCount(0, $xpath->query('//main//select[@*[name()="wire:model.live"]="report"]'));
    }

    public function test_default_and_unknown_report_highlight_profit_trip(): void
    {
        foreach ([[], ['report' => 'unknown']] as $query) {
            $response = $this->actingAs($this->owner)->get(route('reports', $query))
                ->assertOk()->assertSee('Laporan Profit Trip');
            $active = $this->xpath($response->getContent())->query('//aside//a[@aria-current="page"]');
            $this->assertCount(1, $active);
            $this->assertSame(route('reports', ['report' => 'trip-profit']), $active->item(0)->getAttribute('href'));
        }
    }

    public function test_selected_report_survives_period_updates_and_drives_export_links(): void
    {
        Livewire::actingAs($this->owner)
            ->withQueryParams(['report' => 'profit-sharing'])
            ->test(ReportIndex::class)
            ->assertSet('report', 'profit-sharing')
            ->set('preset', 'custom')
            ->set('from', '2026-11-01')
            ->set('to', '2026-11-30')
            ->assertSet('report', 'profit-sharing')
            ->assertSee('Laporan Bagi Hasil')
            ->assertSee(route('reports.export', [
                'report' => 'profit-sharing', 'period' => 'custom',
                'from' => '2026-11-01', 'to' => '2026-11-30', 'format' => 'csv',
            ]));
    }

    private function xpath(string $html): DOMXPath
    {
        $previous = libxml_use_internal_errors(true);
        try {
            $document = new DOMDocument;
            $document->loadHTML('<?xml encoding="UTF-8">'.$html);

            return new DOMXPath($document);
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }
    }
}
