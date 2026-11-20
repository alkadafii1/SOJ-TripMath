<?php

namespace Tests\Unit;

use App\Support\ReportPeriod;
use Illuminate\Support\Carbon;
use PHPUnit\Framework\TestCase;

class ReportPeriodTest extends TestCase
{
    private Carbon $now;

    protected function setUp(): void
    {
        parent::setUp();
        $this->now = Carbon::parse('2026-11-12 14:30:00'); // Kamis
    }

    public function test_periode_siap_pakai(): void
    {
        $today = ReportPeriod::make('today', null, null, $this->now);
        $this->assertSame('2026-11-12', $today->from->toDateString());
        $this->assertSame('2026-11-12', $today->to->toDateString());

        $week = ReportPeriod::make('week', null, null, $this->now);
        $this->assertSame('2026-11-09', $week->from->toDateString()); // Senin
        $this->assertSame('2026-11-15', $week->to->toDateString());   // Minggu

        $month = ReportPeriod::make('month', null, null, $this->now);
        $this->assertSame('2026-11-01', $month->from->toDateString());
        $this->assertSame('2026-11-30', $month->to->toDateString());

        $year = ReportPeriod::make('year', null, null, $this->now);
        $this->assertSame('2026-01-01', $year->from->toDateString());
        $this->assertSame('2026-12-31', $year->to->toDateString());
    }

    public function test_rentang_kustom_mencakup_seluruh_hari_terakhir(): void
    {
        $p = ReportPeriod::make('custom', '2026-10-01', '2026-10-31', $this->now);

        $this->assertSame('custom', $p->preset);
        $this->assertSame('2026-10-01 00:00:00', $p->from->toDateTimeString());
        $this->assertSame('2026-10-31 23:59:59', $p->to->toDateTimeString());
        $this->assertSame('01/10/2026 - 31/10/2026', $p->label());
    }

    public function test_isian_kustom_tidak_valid_jatuh_ke_bulan_ini_dengan_pesan(): void
    {
        foreach ([[null, null], ['', '2026-10-31'], ['2026-10-31', '2026-10-01'], ['bukan-tanggal', '2026-10-01']] as [$from, $to]) {
            $p = ReportPeriod::make('custom', $from, $to, $this->now);
            $this->assertSame('month', $p->preset);
        }

        [$period, $error] = ReportPeriod::resolve('custom', '2026-10-31', '2026-10-01');
        $this->assertSame('month', $period->preset);
        $this->assertStringContainsString('tanggal mulai', $error);

        [, $noError] = ReportPeriod::resolve('year', null, null);
        $this->assertNull($noError);
    }

    public function test_label_satu_hari(): void
    {
        $this->assertSame('12/11/2026', ReportPeriod::make('today', null, null, $this->now)->label());
    }
}
