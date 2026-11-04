<?php

namespace App\Services;

use App\Models\Payment;
use App\Models\Trip;
use App\Models\TripPricing;
use App\Support\Money;
use Illuminate\Support\Facades\DB;

class PaymentService
{
    /** Status yang otomatis mengikuti total pembayaran. Status setelahnya diatur manual. */
    private const AUTO_STATUSES = ['confirmed', 'deposit_paid', 'paid'];

    public function __construct(private FinanceCalculator $finance)
    {
    }

    /** Harga final yang disepakati (dasar Revenue), atau null bila belum ada. */
    public function finalPrice(Trip $trip): ?string
    {
        $price = TripPricing::where('trip_id', $trip->id)->value('final_price');

        return ($price !== null && bccomp((string) $price, '0', 2) > 0) ? (string) $price : null;
    }

    public function paidTotal(Trip $trip): string
    {
        return $this->finance->sum($trip->payments()->active()->pluck('amount')->all());
    }

    /** @return array{final: ?string, paid: string, outstanding: ?string, state: ?string, payments: \Illuminate\Support\Collection} */
    public function summary(Trip $trip): array
    {
        $final = $this->finalPrice($trip);
        $paid = $this->paidTotal($trip);

        return [
            'final' => $final,
            'paid' => $paid,
            'outstanding' => $final !== null ? $this->finance->calculateOutstanding($final, $paid) : null,
            'state' => $final !== null ? $this->finance->paymentState($final, $paid) : null,
            'payments' => $trip->payments()->orderBy('paid_at')->orderBy('id')->get(),
        ];
    }

    /**
     * Mencatat satu pembayaran. Semua aturan dicek di sini, di dalam transaksi.
     *
     * @throws \DomainException bila melanggar aturan
     */
    public function record(Trip $trip, array $data): Payment
    {
        return DB::transaction(function () use ($trip, $data) {
            $fresh = $this->lockedTrip($trip);

            if (! in_array($fresh->status, Trip::PAYABLE, true)) {
                throw new \DomainException('Pembayaran hanya bisa dicatat untuk trip berstatus Confirmed sampai Completed.');
            }

            $final = $this->finalPrice($fresh);
            if ($final === null) {
                throw new \DomainException('Harga final belum ada, pembayaran belum bisa dicatat.');
            }

            $amount = $this->finance->sum([$data['amount']]);
            if (bccomp($amount, '0', 2) <= 0) {
                throw new \DomainException('Jumlah pembayaran harus lebih dari 0.');
            }

            $outstanding = $this->finance->calculateOutstanding($final, $this->paidTotal($fresh));
            if (bccomp($outstanding, '0', 2) <= 0) {
                throw new \DomainException('Trip ini sudah lunas.');
            }
            if (bccomp($amount, $outstanding, 2) > 0) {
                throw new \DomainException('Pembayaran melebihi sisa tagihan ('.Money::rp($outstanding).').');
            }

            $type = $data['payment_type'];
            if ($type === 'deposit' && $fresh->payments()->active()->where('payment_type', 'deposit')->exists()) {
                throw new \DomainException('Deposit sudah dicatat. Gunakan cicilan atau pelunasan.');
            }
            if ($type === 'final' && bccomp($amount, $outstanding, 2) !== 0) {
                throw new \DomainException('Pelunasan harus sebesar sisa tagihan ('.Money::rp($outstanding).').');
            }

            $installmentNo = $type === 'installment'
                ? $fresh->payments()->active()->where('payment_type', 'installment')->count() + 1
                : null;

            $payment = $fresh->payments()->create([
                'payment_type' => $type,
                'installment_no' => $installmentNo,
                'amount' => $amount,
                'paid_at' => $data['paid_at'],
                'method' => $data['method'],
                'reference' => $data['reference'] ?? null,
                'notes' => $data['notes'] ?? null,
            ]);

            AuditLogger::record('payment', $payment->id, 'created', null, [
                'trip_id' => $fresh->id,
                'payment_type' => $type,
                'amount' => $amount,
                'paid_at' => (string) $data['paid_at'],
                'method' => $data['method'],
            ]);

            $this->syncStatus($fresh);

            return $payment;
        });
    }

    /**
     * Membatalkan (void) pembayaran. Data tidak dihapus.
     *
     * @throws \DomainException
     */
    public function void(Payment $payment, string $reason): void
    {
        DB::transaction(function () use ($payment, $reason) {
            $trip = $this->lockedTrip($payment->trip);
            $payment = Payment::findOrFail($payment->id);

            if (! in_array($trip->status, Trip::PAYABLE, true)) {
                throw new \DomainException('Pembayaran tidak bisa dibatalkan pada trip dengan status ini.');
            }
            if ($payment->voided_at !== null) {
                throw new \DomainException('Pembayaran ini sudah dibatalkan.');
            }
            if (trim($reason) === '') {
                throw new \DomainException('Alasan pembatalan wajib diisi.');
            }

            $payment->update(['voided_at' => now(), 'void_reason' => trim($reason)]);

            AuditLogger::record('payment', $payment->id, 'voided',
                ['amount' => (string) $payment->amount, 'voided_at' => null],
                ['voided_at' => now()->toDateTimeString()],
                trim($reason));

            $this->syncStatus($trip);
        });
    }

    /** Menyesuaikan status trip dengan total pembayaran (hanya Confirmed, Deposit Paid, Paid). */
    public function syncStatus(Trip $trip): void
    {
        $trip->refresh();
        if (! in_array($trip->status, self::AUTO_STATUSES, true)) {
            return;
        }

        $final = $this->finalPrice($trip);
        if ($final === null) {
            return;
        }

        $target = match ($this->finance->paymentState($final, $this->paidTotal($trip))) {
            'unpaid' => 'confirmed',
            'partial' => 'deposit_paid',
            default => 'paid',
        };

        if ($target !== $trip->status) {
            $old = $trip->status;
            $trip->update(['status' => $target]);
            AuditLogger::record('trip', $trip->id, 'status_changed', ['status' => $old], ['status' => $target], 'Otomatis mengikuti pembayaran');
        }
    }

    private function lockedTrip(Trip $trip): Trip
    {
        return Trip::whereKey($trip->id)->lockForUpdate()->firstOrFail();
    }
}