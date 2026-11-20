<?php

namespace Tests\Unit;

use App\Services\FinanceCalculator;
use App\Services\PricingCalculator;
use App\Services\TripCalculator;
use PHPUnit\Framework\TestCase;

class FinanceCalculatorTest extends TestCase
{
    private FinanceCalculator $finance;
    private TripCalculator $trip;

    protected function setUp(): void
    {
        parent::setUp();
        $this->finance = new FinanceCalculator(new PricingCalculator());
        $this->trip = new TripCalculator();
    }

    private function assertMoney($expected, ?string $actual): void
    {
        $this->assertNotNull($actual);
        $this->assertSame(0, bccomp((string) $expected, $actual, 4), "Diharapkan {$expected}, hasilnya {$actual}");
    }

    public function test_sisa_tagihan_dan_status_pembayaran(): void
    {
        $this->assertMoney('9500000', $this->finance->calculateOutstanding('9500000', '0'));
        $this->assertMoney('6500000', $this->finance->calculateOutstanding('9500000', '3000000'));
        $this->assertMoney('0', $this->finance->calculateOutstanding('9500000', '9500000'));

        $this->assertSame('unpaid', $this->finance->paymentState('9500000', '0'));
        $this->assertSame('partial', $this->finance->paymentState('9500000', '3000000'));
        $this->assertSame('paid', $this->finance->paymentState('9500000', '9500000'));
        $this->assertSame('overpaid', $this->finance->paymentState('9500000', '9500001'));
    }

    public function test_jumlah_pembayaran(): void
    {
        $this->assertMoney('4750000.50', $this->finance->sum(['3000000', '1750000.25', '0.25']));
        $this->assertMoney('0', $this->finance->sum([]));
    }

    public function test_selisih_biaya_contoh_spesifikasi(): void
    {
        // Negotiated 6.890.000, Actual 6.950.000 => lebih mahal Rp60.000
        $neg = $this->finance->calculateVariance('6890000', '6950000');
        $this->assertMoney('60000', $neg['amount']);
        $this->assertMoney('0.8708', $neg['percent']);

        // Standard 7.740.000 => Actual lebih murah Rp790.000
        $std = $this->finance->calculateVariance('7740000', '6950000');
        $this->assertMoney('-790000', $std['amount']);
        $this->assertMoney('-10.2067', $std['percent']);

        // Biaya tambahan: pembanding nol, persen tidak terdefinisi
        $extra = $this->finance->calculateVariance('0', '150000');
        $this->assertMoney('150000', $extra['amount']);
        $this->assertNull($extra['percent']);
    }

    public function test_profit_aktual_contoh_spesifikasi(): void
    {
        // Final 9.500.000, Actual 6.950.000, 4 pax => profit 2.550.000
        $r = $this->finance->calculateProfit('9500000', '6950000', 4);

        $this->assertMoney('2550000', $r['profit']);
        $this->assertMoney('26.8421', $r['margin']);
        $this->assertMoney('637500', $r['per_pax']);

        $this->assertNull($this->finance->calculateProfit('0', '1000', 4)['margin']);
    }

    public function test_biaya_aktual_dijumlah_per_komponen_dan_yang_wajib_kosong_dilaporkan(): void
    {
        $items = [
            1 => ['category' => 'Akomodasi', 'actual_cost' => '1500000', 'is_required' => true],
            2 => ['category' => 'Makan', 'actual_cost' => '300000.50', 'is_required' => true],
            3 => ['category' => 'Makan', 'actual_cost' => '100000', 'is_required' => true],
            4 => ['category' => 'Tiket', 'actual_cost' => null, 'is_required' => true],   // wajib, belum diisi
            5 => ['category' => 'Lainnya', 'actual_cost' => null, 'is_required' => false], // opsional, dianggap 0
            6 => ['category' => 'Tiket', 'actual_cost' => '0', 'is_required' => true],      // diisi 0 = sah
        ];

        $r = $this->trip->calculateActualCost($items);

        $this->assertMoney('1900000.50', $r['total']);
        $this->assertMoney('400000.50', $r['by_category']['Makan']);
        $this->assertSame([4], $r['missing']);
    }

    public function test_waterfall_contoh_spesifikasi_bagian_20(): void
    {
        // Revenue 10.000.000, Actual Cost 6.890.000, marketing 5% dari Revenue, Owner 60%, Partner 40%
        $profit = $this->finance->calculateProfit('10000000', '6890000', 4)['profit'];
        $this->assertMoney('3110000', $profit);

        $marketing = $this->finance->calculateMarketingAllocation('10000000', $profit, '5', 'revenue');
        $this->assertMoney('500000', $marketing);

        $distributable = $this->finance->calculateDistributable($profit, $marketing);
        $this->assertMoney('2610000', $distributable);

        $shares = $this->finance->calculateProfitDistribution($distributable, ['owner' => '60', 'partner' => '40']);
        $this->assertMoney('1566000', $shares['owner']['amount']);
        $this->assertMoney('1044000', $shares['partner']['amount']);
    }

    public function test_skenario_contoh_spesifikasi_bagian_46(): void
    {
        // Final 9.500.000, Actual 6.950.000 => profit 2.550.000; marketing 5% Revenue = 475.000
        $profit = $this->finance->calculateProfit('9500000', '6950000', 4)['profit'];
        $marketing = $this->finance->calculateMarketingAllocation('9500000', $profit, '5', 'revenue');
        $distributable = $this->finance->calculateDistributable($profit, $marketing);
        $shares = $this->finance->calculateProfitDistribution($distributable, ['owner' => '60', 'partner' => '40']);

        $this->assertMoney('475000', $marketing);
        $this->assertMoney('2075000', $distributable);
        $this->assertMoney('1245000', $shares['owner']['amount']);
        $this->assertMoney('830000', $shares['partner']['amount']);
    }

    public function test_marketing_dari_actual_profit(): void
    {
        $marketing = $this->finance->calculateMarketingAllocation('9500000', '2550000', '5', 'actual_profit');
        $this->assertMoney('127500', $marketing);

        $distributable = $this->finance->calculateDistributable('2550000', $marketing);
        $shares = $this->finance->calculateProfitDistribution($distributable, ['owner' => '60', 'partner' => '40']);

        $this->assertMoney('2422500', $distributable);
        $this->assertMoney('1453500', $shares['owner']['amount']);
        $this->assertMoney('969000', $shares['partner']['amount']);
    }

    public function test_trip_merugi_marketing_dari_profit_nol_tetapi_dari_revenue_tetap_ada(): void
    {
        $this->assertMoney('0', $this->finance->calculateMarketingAllocation('9500000', '-500000', '5', 'actual_profit'));
        $this->assertMoney('475000', $this->finance->calculateMarketingAllocation('9500000', '-500000', '5', 'revenue'));

        // Kerugian dibagi dengan persentase yang sama
        $shares = $this->finance->calculateProfitDistribution('-500000', ['owner' => '60', 'partner' => '40']);
        $this->assertMoney('-300000', $shares['owner']['amount']);
        $this->assertMoney('-200000', $shares['partner']['amount']);
    }

    public function test_sisa_pembulatan_masuk_ke_owner_dan_jumlah_bagian_selalu_pas(): void
    {
        foreach (['100.01', '0.03', '1234567.89', '-100.01', '999999.99'] as $distributable) {
            $shares = $this->finance->calculateProfitDistribution($distributable, ['owner' => '60', 'partner' => '40']);
            $sum = $this->finance->sum([$shares['owner']['amount'], $shares['partner']['amount']]);

            $this->assertMoney($distributable, $sum);
        }

        $shares = $this->finance->calculateProfitDistribution('100.01', ['owner' => '60', 'partner' => '40']);
        $this->assertMoney('40.00', $shares['partner']['amount']); // 40,004 dibulatkan
        $this->assertMoney('60.01', $shares['owner']['amount']);   // sisa sen ke Owner

        // Tiga pihak, tetap pas
        $three = $this->finance->calculateProfitDistribution('100', ['owner' => '33.3334', 'a' => '33.3333', 'b' => '33.3333']);
        $this->assertMoney('100', $this->finance->sum(array_column($three, 'amount')));
    }

    public function test_total_bagi_hasil_wajib_100(): void
    {
        $this->assertTrue($this->finance->isTotalHundred(['owner' => '60', 'partner' => '40']));
        $this->assertTrue($this->finance->isTotalHundred(['a' => '33.3334', 'b' => '33.3333', 'c' => '33.3333']));
        $this->assertFalse($this->finance->isTotalHundred(['owner' => '60', 'partner' => '39.99']));
        $this->assertMoney('99.99', $this->finance->sumPercent(['60', '39.99']));

        $this->expectException(\InvalidArgumentException::class);
        $this->finance->calculateProfitDistribution('1000000', ['owner' => '60', 'partner' => '39']);
    }

    public function test_dasar_marketing_dan_pihak_sisa_tidak_dikenal_ditolak(): void
    {
        $failed = 0;
        foreach ([
            fn () => $this->finance->calculateMarketingAllocation('1000', '100', '5', 'ngawur'),
            fn () => $this->finance->calculateProfitDistribution('1000', ['owner' => '60', 'partner' => '40'], 'marketing'),
        ] as $action) {
            try {
                $action();
            } catch (\InvalidArgumentException) {
                $failed++;
            }
        }

        $this->assertSame(2, $failed);
    }
}