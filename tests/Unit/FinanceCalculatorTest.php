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
}