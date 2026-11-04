<?php

namespace Tests\Unit;

use App\Services\PricingCalculator;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class PricingCalculatorTest extends TestCase
{
    private PricingCalculator $calc;

    protected function setUp(): void
    {
        parent::setUp();
        $this->calc = new PricingCalculator();
    }

    private function assertMoney($expected, ?string $actual): void
    {
        $this->assertNotNull($actual);
        $this->assertSame(0, bccomp((string) $expected, $actual, 4), "Diharapkan {$expected}, hasilnya {$actual}");
    }

    public function test_cost_plus_margin_contoh_spesifikasi(): void
    {
        // Negotiated Cost 6.890.000 dengan margin 31% => 9.985.507,25 => dibulatkan Rp10.000.000
        $this->assertMoney('9985507.25', $this->calc->calculateRecommendedPrice('6890000', 'cost_plus_margin', '31', 'exact'));
        $this->assertMoney('10000000', $this->calc->calculateRecommendedPrice('6890000', 'cost_plus_margin', '31', 'nearest_100000'));
    }

    public function test_margin_dan_markup_adalah_dua_hal_berbeda(): void
    {
        $margin = $this->calc->calculateRecommendedPrice('1000000', 'cost_plus_margin', '30');
        $markup = $this->calc->calculateRecommendedPrice('1000000', 'cost_plus_markup', '30');

        $this->assertMoney('1428571.43', $margin);
        $this->assertMoney('1300000', $markup);
        $this->assertNotEquals($margin, $markup);
    }

    public function test_aturan_pembulatan(): void
    {
        $this->assertMoney('9990000', $this->calc->roundPrice('9990500', 'nearest_5000'));
        $this->assertMoney('9990000', $this->calc->roundPrice('9990500', 'nearest_10000'));
        $this->assertMoney('10000000', $this->calc->roundPrice('9990500', 'nearest_50000'));
        $this->assertMoney('10000000', $this->calc->roundPrice('9990500', 'nearest_100000'));
        $this->assertMoney('10000000', $this->calc->roundPrice('9995000', 'nearest_10000')); // tepat di tengah: ke atas
        $this->assertMoney('9990500', $this->calc->roundPrice('9990500', 'exact'));
    }

    public function test_target_margin_tidak_valid_ditolak_tetapi_markup_boleh_di_atas_100(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->calc->calculateRecommendedPrice('1000000', 'cost_plus_margin', '100');
    }

    public function test_markup_di_atas_100_persen_diizinkan(): void
    {
        $this->assertMoney('2500000', $this->calc->calculateRecommendedPrice('1000000', 'cost_plus_markup', '150'));
    }

    public function test_metode_dan_pembulatan_tidak_dikenal_ditolak(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->calc->roundPrice('1000', 'ngawur');
    }

    public function test_vendor_saving_contoh_spesifikasi(): void
    {
        $r = $this->calc->calculateVendorSaving('7740000', '6890000');

        $this->assertMoney('850000', $r['amount']);
        $this->assertMoney('10.9819', $r['percent']);
    }

    public function test_customer_discount_dan_profit_estimasi(): void
    {
        $this->assertMoney('500000', $this->calc->calculateCustomerDiscount('10000000', '9500000'));
        $this->assertMoney('2610000', $this->calc->calculateEstimatedProfit('9500000', '6890000'));
        $this->assertMoney('27.4736', $this->calc->calculateMargin('9500000', '6890000'));
        $this->assertNull($this->calc->calculateMargin('0', '1000'));
    }

    public function test_batas_margin_minimum(): void
    {
        $this->assertTrue($this->calc->isBelowMinimum('27.4736', '30'));
        $this->assertFalse($this->calc->isBelowMinimum('27.4736', '25'));
        $this->assertFalse($this->calc->isBelowMinimum('25.0000', '25')); // tepat di batas bukan "di bawah"
        $this->assertFalse($this->calc->isBelowMinimum('10', null));      // batas tidak diatur
        $this->assertTrue($this->calc->isBelowMinimum(null, '25'));       // harga nol
    }

        public function test_pembulatan_uang_setengah_menjauhi_nol(): void
    {
        $this->assertSame('1234.57', $this->calc->roundMoney('1234.5678'));
        $this->assertSame('1234.56', $this->calc->roundMoney('1234.5649'));
        $this->assertSame('-1234.57', $this->calc->roundMoney('-1234.5678'));
        $this->assertSame('0.00', $this->calc->roundMoney('-0.0004'));
        $this->assertSame('0.00', $this->calc->roundMoney(null));
    }
}