<?php

namespace Tests\Unit;

use App\Services\TripCalculator;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class TripCalculatorTest extends TestCase
{
    private TripCalculator $calc;

    protected function setUp(): void
    {
        parent::setUp();
        $this->calc = new TripCalculator();
    }

    private function assertMoney($expected, string $actual): void
    {
        $this->assertSame(0, bccomp((string) $expected, $actual, 4), "Diharapkan {$expected}, hasilnya {$actual}");
    }

    private function item(array $overrides = []): array
    {
        return array_merge([
            'category' => 'Lainnya', 'cost_type' => 'per_trip', 'cost_behavior' => 'fixed',
            'quantity' => 1, 'duration_qty' => 1, 'unit_capacity' => null,
            'override_quantity' => null, 'percentage' => null, 'base_type' => null,
            'standard_cost' => 0, 'negotiated_cost' => null, 'actual_cost' => null,
            'people_count' => null, // Ditambahkan untuk mendukung test case baru
        ], $overrides);
    }

    public function test_kebutuhan_kamar_dibulatkan_ke_atas(): void
    {
        $this->assertSame(2, $this->calc->calculateRoomRequirement(4, 2));
        $this->assertSame(3, $this->calc->calculateRoomRequirement(5, 2));
        $this->assertSame(3, $this->calc->calculateRoomRequirement(6, 2));
        $this->assertSame(0, $this->calc->calculateRoomRequirement(0, 2));
    }

    public function test_pax_berubah_biaya_variable_naik_biaya_fixed_tetap(): void
    {
        $items = [
            'tiket' => $this->item(['category' => 'Tiket', 'cost_type' => 'per_person', 'cost_behavior' => 'variable', 'standard_cost' => 100000]),
            'guide' => $this->item(['category' => 'Guide', 'cost_type' => 'per_trip', 'cost_behavior' => 'fixed', 'standard_cost' => 500000]),
            'hotel' => $this->item(['category' => 'Akomodasi', 'cost_type' => 'per_room', 'cost_behavior' => 'variable', 'unit_capacity' => 2, 'standard_cost' => 400000]),
        ];

        $four = $this->calc->calculateTripCost(4, $items);
        $this->assertMoney(1700000, $four['total']);      // 400rb + 500rb + 2 kamar x 400rb
        $this->assertMoney(500000, $four['fixed_total']);
        $this->assertMoney(1200000, $four['variable_total']);

        $six = $this->calc->calculateTripCost(6, $items);
        $this->assertMoney(2300000, $six['total']);       // 600rb + 500rb + 3 kamar x 400rb
        $this->assertMoney(500000, $six['fixed_total']);  // biaya tetap TIDAK ikut naik
        $this->assertMoney(1800000, $six['variable_total']);
    }

    public function test_override_kamar_memakai_nilai_manual_dan_menyimpan_nilai_hitungan(): void
    {
        $items = ['h' => $this->item(['cost_type' => 'per_room', 'unit_capacity' => 2, 'standard_cost' => 400000, 'override_quantity' => 3])];

        $r = $this->calc->calculateTripCost(4, $items);

        $this->assertSame(2, $r['lines']['h']['calculated_quantity']);
        $this->assertMoney(3, $r['lines']['h']['units']);
        $this->assertMoney(1200000, $r['total']);
    }

    public function test_hotel_dikalikan_jumlah_malam(): void
    {
        $items = ['h' => $this->item(['cost_type' => 'per_room', 'unit_capacity' => 2, 'standard_cost' => 400000, 'duration_qty' => 2])];

        $this->assertMoney(1600000, $this->calc->calculateTripCost(4, $items)['total']); // 2 kamar x 2 malam
    }

    public function test_kendaraan_harian_menambah_unit_saat_pax_melewati_kapasitas(): void
    {
        $items = ['bus' => $this->item(['cost_type' => 'per_day', 'cost_behavior' => 'variable', 'unit_capacity' => 5, 'standard_cost' => 1000000, 'duration_qty' => 2])];

        $this->assertMoney(2000000, $this->calc->calculateTripCost(5, $items)['total']); // 1 unit x 2 hari
        $this->assertMoney(4000000, $this->calc->calculateTripCost(6, $items)['total']); // 2 unit x 2 hari
    }

    public function test_biaya_persentase_dari_biaya_dan_dari_revenue(): void
    {
        $base = $this->item(['cost_type' => 'per_trip', 'standard_cost' => 1000000]);

        $fromCost = $this->calc->calculateTripCost(2, [
            'a' => $base,
            'p' => $this->item(['cost_type' => 'percentage', 'percentage' => 5, 'base_type' => 'cost']),
        ]);
        $this->assertMoney(1050000, $fromCost['total']);

        $fromRevenue = $this->calc->calculateTripCost(2, [
            'a' => $base,
            'p' => $this->item(['cost_type' => 'percentage', 'percentage' => 5, 'base_type' => 'revenue']),
        ], 'standard_cost', '10000000');
        $this->assertMoney(1500000, $fromRevenue['total']);

        $noRevenue = $this->calc->calculateTripCost(2, [
            'a' => $base,
            'p' => $this->item(['cost_type' => 'percentage', 'percentage' => 5, 'base_type' => 'revenue']),
        ]);
        $this->assertMoney(1000000, $noRevenue['total']);
    }

    public function test_negosiasi_mengubah_negotiated_tapi_standard_tetap(): void
    {
        $items = [
            'a' => $this->item(['cost_type' => 'per_person', 'standard_cost' => 100000, 'negotiated_cost' => 90000]),
            'b' => $this->item(['cost_type' => 'per_trip', 'standard_cost' => 50000]), // belum dinegosiasikan
        ];

        $standard = $this->calc->calculateTripCost(2, $items, 'standard_cost');
        $negotiated = $this->calc->calculateTripCost(2, $items, 'negotiated_cost');

        $this->assertMoney(250000, $standard['total']);
        $this->assertMoney(230000, $negotiated['total']);
        $this->assertMoney(20000, bcsub($standard['total'], $negotiated['total'], 4)); // vendor saving
    }

    public function test_subtotal_per_kategori_dan_biaya_per_orang(): void
    {
        $items = [
            'a' => $this->item(['category' => 'Tiket', 'cost_type' => 'per_person', 'standard_cost' => 100000]),
            'b' => $this->item(['category' => 'Tiket', 'cost_type' => 'per_trip', 'standard_cost' => 20000]),
            'c' => $this->item(['category' => 'Guide', 'cost_type' => 'per_trip', 'standard_cost' => 300000]),
        ];

        $r = $this->calc->calculateTripCost(4, $items);

        $this->assertMoney(420000, $r['by_category']['Tiket']);
        $this->assertMoney(300000, $r['by_category']['Guide']);
        $this->assertMoney(180000, $r['cost_per_person']);
    }

    public function test_tipe_biaya_tidak_dikenal_ditolak(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->calc->calculateTripCost(2, ['x' => $this->item(['cost_type' => 'ngawur'])]);
    }

    public function test_fotografer_per_hari_dan_jeep_per_unit_tidak_dikali_pax(): void
    {
        $items = [
            'foto' => $this->item(['cost_type' => 'per_day', 'standard_cost' => 150000, 'quantity' => 1, 'duration_qty' => 2]),
            'jeep' => $this->item(['cost_type' => 'per_vehicle', 'standard_cost' => 120000, 'quantity' => 5]),
        ];

        $r = $this->calc->calculateTripCost(10, $items);

        $this->assertMoney(300000, $r['lines']['foto']['amount']);
        $this->assertMoney(600000, $r['lines']['jeep']['amount']);
        $this->assertMoney(900000, $r['total']);
    }

    public function test_biaya_per_orang_hanya_untuk_sebagian_peserta(): void
    {
        $items = ['makan' => $this->item([
            'cost_type' => 'per_person', 'cost_behavior' => 'fixed',
            'standard_cost' => 25000, 'people_count' => 5,
        ])];

        $this->assertMoney(125000, $this->calc->calculateTripCost(10, $items)['total']); // 5 orang x 25.000
        $this->assertMoney(125000, $this->calc->calculateTripCost(20, $items)['total']); // pax naik, biaya tetap
    }

    public function test_tanpa_jumlah_orang_biaya_per_orang_berlaku_untuk_semua_peserta(): void
    {
        $items = ['tiket' => $this->item([
            'cost_type' => 'per_person', 'cost_behavior' => 'variable', 'standard_cost' => 50000,
        ])];

        $this->assertMoney(500000, $this->calc->calculateTripCost(10, $items)['total']);
        $this->assertMoney(1000000, $this->calc->calculateTripCost(20, $items)['total']);
    }

    public function test_biaya_per_orang_tidak_memakai_pengali(): void
    {
        $items = ['tiket' => $this->item([
            'cost_type' => 'per_person', 'cost_behavior' => 'variable',
            'standard_cost' => 50000, 'quantity' => 5,
    
        ])];

        $this->assertMoney(500000, $this->calc->calculateTripCost(10, $items)['total']);
    }

    public function test_per_hari_dikali_jumlah_unit_dan_jumlah_hari(): void
    {
        $items = ['foto' => $this->item([
            'cost_type' => 'per_day', 'standard_cost' => 150000, 'quantity' => 2, 'duration_qty' => 3,
        ])];

        $this->assertMoney(900000, $this->calc->calculateTripCost(10, $items)['total']); // 2 x 3 x 150.000
    }
}