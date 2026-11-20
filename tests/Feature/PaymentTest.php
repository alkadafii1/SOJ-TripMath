<?php

namespace Tests\Feature;

use App\Livewire\Trip\PaymentPage;
use App\Models\Payment;
use App\Models\Trip;
use App\Models\TripPricing;
use App\Models\User;
use App\Services\PaymentService;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class PaymentTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;
    private User $staff;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
        $this->owner = $this->userWithRole('owner', true);
        $this->staff = $this->userWithRole('staff');
    }

    private function userWithRole(string $role, bool $finance = false): User
    {
        $user = User::factory()->create(['role' => $role, 'financial_visibility' => $finance]);
        $user->assignRole($role);

        return $user;
    }

    private function trip(string $status = 'confirmed', ?string $final = '9500000', array $overrides = []): Trip
    {
        $trip = Trip::create(array_merge([
            'trip_code' => 'SOJ-T-'.uniqid(), 'trip_name' => 'Tes', 'customer_name' => 'Budi',
            'trip_type' => 'private', 'start_date' => '2026-11-01', 'end_date' => '2026-11-02',
            'duration_days' => 2, 'nights' => 1, 'pax' => 4, 'status' => $status, 'created_by' => $this->owner->id,
        ], $overrides));

        if ($final !== null) {
            TripPricing::create([
                'trip_id' => $trip->id, 'pricing_mode' => 'dynamic', 'pricing_method' => 'cost_plus_margin',
                'rate_percent' => 30, 'recommended_price' => $final, 'final_price' => $final,
            ]);
        }

        return $trip;
    }

    private function pay(Trip $trip, string $type, string $amount, array $extra = []): Payment
    {
        return app(PaymentService::class)->record($trip, array_merge([
            'payment_type' => $type, 'amount' => $amount, 'paid_at' => '2026-10-30', 'method' => 'transfer',
        ], $extra));
    }

    private function assertRejected(callable $action, string $messagePart): void
    {
        try {
            $action();
        } catch (\DomainException $e) {
            $this->assertStringContainsString($messagePart, $e->getMessage());

            return;
        }

        $this->fail("Seharusnya ditolak dengan pesan yang memuat: {$messagePart}");
    }

    public function test_deposit_mengubah_status_menjadi_deposit_paid_dan_tercatat(): void
    {
        $trip = $this->trip();

        $payment = $this->pay($trip, 'deposit', '3000000', ['reference' => 'TRF-001']);

        $this->assertSame('deposit_paid', $trip->fresh()->status);
        $summary = app(PaymentService::class)->summary($trip);
        $this->assertEquals(3000000, (float) $summary['paid']);
        $this->assertEquals(6500000, (float) $summary['outstanding']);
        $this->assertSame('partial', $summary['state']);
        $this->assertDatabaseHas('audit_logs', ['entity' => 'payment', 'entity_id' => $payment->id, 'action' => 'created']);
        $this->assertDatabaseHas('audit_logs', ['entity' => 'trip', 'entity_id' => $trip->id, 'action' => 'status_changed', 'reason' => 'Otomatis mengikuti pembayaran']);
    }

    public function test_cicilan_bernomor_dan_pelunasan_membuat_trip_lunas(): void
    {
        $trip = $this->trip();

        $this->pay($trip, 'deposit', '3000000');
        $first = $this->pay($trip, 'installment', '2000000');
        $second = $this->pay($trip, 'installment', '1500000');

        $this->assertSame(1, $first->installment_no);
        $this->assertSame(2, $second->installment_no);

        // Sisa 3.000.000: pelunasan harus tepat sebesar sisa
        $this->assertRejected(fn () => $this->pay($trip, 'final', '2999999'), 'Pelunasan harus sebesar sisa tagihan');

        $this->pay($trip, 'final', '3000000');

        $this->assertSame('paid', $trip->fresh()->status);
        $this->assertSame('paid', app(PaymentService::class)->summary($trip)['state']);
        $this->assertEquals(0, (float) app(PaymentService::class)->summary($trip)['outstanding']);
        $this->assertRejected(fn () => $this->pay($trip, 'installment', '1'), 'sudah lunas');
    }

    public function test_pembayaran_melebihi_sisa_nol_atau_deposit_ganda_ditolak(): void
    {
        $trip = $this->trip();

        $this->assertRejected(fn () => $this->pay($trip, 'deposit', '9500001'), 'melebihi sisa tagihan');
        $this->assertRejected(fn () => $this->pay($trip, 'deposit', '0'), 'lebih dari 0');

        $this->pay($trip, 'deposit', '1000000');
        $this->assertRejected(fn () => $this->pay($trip, 'deposit', '500000'), 'Deposit sudah dicatat');

        $this->assertSame(1, Payment::count());
        $this->assertEquals(1000000, (float) app(PaymentService::class)->paidTotal($trip));
    }

    public function test_void_wajib_alasan_data_tetap_ada_dan_status_menyesuaikan(): void
    {
        $trip = $this->trip();
        $deposit = $this->pay($trip, 'deposit', '3000000');
        $final = $this->pay($trip, 'final', '6500000');
        $this->assertSame('paid', $trip->fresh()->status);

        $this->assertRejected(fn () => app(PaymentService::class)->void($final, '  '), 'Alasan');

        app(PaymentService::class)->void($final, 'Salah catat nominal');

        $this->assertSame('deposit_paid', $trip->fresh()->status);
        $this->assertNotNull($final->fresh()->voided_at);
        $this->assertSame('Salah catat nominal', $final->fresh()->void_reason);
        $this->assertSame(2, Payment::count()); // tidak dihapus
        $this->assertEquals(3000000, (float) app(PaymentService::class)->paidTotal($trip));
        $this->assertDatabaseHas('audit_logs', ['entity' => 'payment', 'entity_id' => $final->id, 'action' => 'voided', 'reason' => 'Salah catat nominal']);

        app(PaymentService::class)->void($deposit, 'Dobel input');
        $this->assertSame('confirmed', $trip->fresh()->status);
        $this->assertRejected(fn () => app(PaymentService::class)->void($deposit, 'Lagi'), 'sudah dibatalkan');
    }

    public function test_pembayaran_hanya_bisa_dicatat_pada_status_yang_sesuai(): void
    {
        foreach (['draft', 'estimated', 'negotiation', 'reconciled', 'cancelled'] as $status) {
            $trip = $this->trip($status);
            $this->assertRejected(fn () => $this->pay($trip, 'deposit', '1000000'), 'berstatus Confirmed sampai Completed');
        }

        // Setelah trip berjalan, pelunasan masih boleh dicatat dan status tidak diubah otomatis
        $ongoing = $this->trip('ongoing');
        $this->pay($ongoing, 'deposit', '1000000');
        $this->assertSame('ongoing', $ongoing->fresh()->status);

        $completed = $this->trip('completed');
        $this->pay($completed, 'deposit', '1000000');
        $this->assertSame('completed', $completed->fresh()->status);
    }

    public function test_tanpa_harga_final_pembayaran_ditolak(): void
    {
        $trip = $this->trip('confirmed', null);

        $this->assertRejected(fn () => $this->pay($trip, 'deposit', '1000000'), 'Harga final belum ada');
    }

    public function test_hak_akses_halaman_pembayaran(): void
    {
        $partnerFinance = $this->userWithRole('partner', true);
        $partnerPlain = $this->userWithRole('partner');
        $trip = $this->trip('confirmed', '9500000', ['assigned_partner_id' => $partnerFinance->id]);

        $this->actingAs($this->owner)->get(route('trips.payments', $trip->id))->assertOk();
        $this->actingAs($partnerFinance)->get(route('trips.payments', $trip->id))->assertOk();
        $this->actingAs($this->staff)->get(route('trips.payments', $trip->id))->assertForbidden();
        $this->actingAs($partnerPlain)->get(route('trips.payments', $trip->id))->assertForbidden();

        // Melihat saja tidak cukup untuk mencatat pembayaran
        Livewire::actingAs($partnerFinance)->test(PaymentPage::class, ['trip' => $trip])
            ->set('form.amount', '1000000')
            ->call('record')
            ->assertForbidden();
        $this->assertSame(0, Payment::count());
    }

    public function test_form_pembayaran_di_halaman_menyimpan_dan_menolak_input_salah(): void
    {
        $trip = $this->trip();
        $page = Livewire::actingAs($this->owner)->test(PaymentPage::class, ['trip' => $trip]);

        $page->set('form.amount', '3000000')->set('form.paid_at', now()->addDay()->toDateString())
            ->call('record')->assertHasErrors(['form.paid_at']);
        $page->set('form.paid_at', now()->toDateString())->set('form.amount', 'abc')
            ->call('record')->assertHasErrors(['form.amount']);
        $page->set('form.amount', '99999999')->call('record')->assertHasErrors(['form.amount']);
        $this->assertSame(0, Payment::count());

        $page->set('form.amount', '3000000')->call('record')->assertHasNoErrors();

        $this->assertSame(1, Payment::count());
        $this->assertSame('deposit_paid', $trip->fresh()->status);
        $page->assertSee('Dibayar sebagian');
    }

    public function test_pelunasan_di_halaman_otomatis_terisi_sebesar_sisa_tagihan(): void
    {
        $trip = $this->trip();
        $this->pay($trip, 'deposit', '3500000');

        Livewire::actingAs($this->owner)->test(PaymentPage::class, ['trip' => $trip])
            ->set('form.payment_type', 'final')
            ->assertSet('form.amount', '6000000');
    }

    public function test_tombol_status_trip_mengikuti_alur_pembayaran(): void
    {
        $trip = $this->trip('confirmed');
        $page = Livewire::actingAs($this->staff)->test(\App\Livewire\Trip\TripShow::class, ['trip' => $trip]);

        // Belum ada pembayaran: tidak boleh langsung berjalan
        $page->call('changeStatus', 'ongoing');
        $this->assertSame('confirmed', $trip->fresh()->status);

        $this->pay($trip, 'deposit', '3000000');
        $page->call('changeStatus', 'ongoing');
        $this->assertSame('ongoing', $trip->fresh()->status);

        $page->call('changeStatus', 'completed');
        $this->assertSame('completed', $trip->fresh()->status);

        // Tidak boleh mundur atau melompat ke Reconciled lewat halaman ini
        $page->call('changeStatus', 'ongoing');
        $page->call('changeStatus', 'reconciled');
        $this->assertSame('completed', $trip->fresh()->status);
    }
}
