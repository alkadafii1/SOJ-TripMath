<?php

namespace App\Livewire\Trip;

use App\Models\Payment;
use App\Models\Trip;
use App\Services\PaymentService;
use App\Support\Options;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Livewire\Component;

class PaymentPage extends Component
{
    public Trip $trip;

    public array $form = [
        'payment_type' => 'deposit', 'amount' => '', 'paid_at' => '',
        'method' => 'transfer', 'reference' => '', 'notes' => '',
    ];

    public ?int $voidingId = null;
    public string $voidReason = '';

    public function mount(Trip $trip): void
    {
        abort_unless(Gate::allows('trip.view') && Gate::allows('view-finance'), 403);
        abort_unless(Trip::visibleTo(Auth::user())->whereKey($trip->id)->exists(), 403);

        $this->trip = $trip;
        $this->resetForm();
    }

    protected function messages(): array
    {
        return [
            'required' => ':attribute wajib diisi.',
            'numeric' => ':attribute harus berupa angka.',
            'gt' => ':attribute harus lebih dari 0.',
            'in' => ':attribute tidak valid.',
            'date' => ':attribute bukan tanggal yang valid.',
            'before_or_equal' => ':attribute tidak boleh di masa depan.',
            'max.string' => ':attribute terlalu panjang (maksimal :max karakter).',
            'min.string' => ':attribute minimal :min karakter.',
        ];
    }

    protected function validationAttributes(): array
    {
        return [
            'form.payment_type' => 'jenis pembayaran',
            'form.amount' => 'jumlah',
            'form.paid_at' => 'tanggal bayar',
            'form.method' => 'metode',
            'form.reference' => 'referensi',
            'form.notes' => 'catatan',
            'voidReason' => 'alasan pembatalan',
        ];
    }

    protected function service(): PaymentService
    {
        return app(PaymentService::class);
    }

    protected function resetForm(): void
    {
        $hasDeposit = $this->trip->payments()->active()->where('payment_type', 'deposit')->exists();

        $this->form = [
            'payment_type' => $hasDeposit ? 'installment' : 'deposit',
            'amount' => '',
            'paid_at' => now()->toDateString(),
            'method' => 'transfer',
            'reference' => '',
            'notes' => '',
        ];
        $this->resetErrorBag();
    }

    /** Pelunasan otomatis terisi sebesar sisa tagihan. */
    public function updatedForm($value, $key): void
    {
        if ($key === 'payment_type' && $value === 'final') {
            $outstanding = $this->service()->summary($this->trip)['outstanding'];
            $this->form['amount'] = $outstanding !== null ? $this->plain($outstanding) : '';
        }
    }

    public function fillOutstanding(): void
    {
        $outstanding = $this->service()->summary($this->trip)['outstanding'];
        $this->form['amount'] = $outstanding !== null ? $this->plain($outstanding) : '';
    }

    public function record(): void
    {
        abort_unless(Gate::allows('payment.manage'), 403);

        $data = $this->validate([
            'form.payment_type' => ['required', 'in:'.Options::keys(Options::PAYMENT_TYPES)],
            'form.amount' => ['required', 'numeric', 'gt:0', 'decimal:0,2'],
            'form.paid_at' => ['required', 'date', 'before_or_equal:today'],
            'form.method' => ['required', 'in:'.Options::keys(Options::PAYMENT_METHODS)],
            'form.reference' => ['nullable', 'string', 'max:100'],
            'form.notes' => ['nullable', 'string', 'max:500'],
        ])['form'];

        try {
            $this->service()->record($this->trip, [
                'payment_type' => $data['payment_type'],
                'amount' => (string) $data['amount'],
                'paid_at' => $data['paid_at'],
                'method' => $data['method'],
                'reference' => trim((string) ($data['reference'] ?? '')) ?: null,
                'notes' => trim((string) ($data['notes'] ?? '')) ?: null,
            ]);
        } catch (\DomainException $e) {
            $this->addError('form.amount', $e->getMessage());

            return;
        }

        $this->trip->refresh();
        $this->resetForm();
        session()->flash('status', 'Pembayaran dicatat. Status trip: '.(Trip::STATUS[$this->trip->status] ?? $this->trip->status).'.');
    }

    public function startVoid(int $id): void
    {
        abort_unless(Gate::allows('payment.manage'), 403);

        $this->voidingId = $this->trip->payments()->active()->findOrFail($id)->id;
        $this->voidReason = '';
        $this->resetErrorBag();
    }

    public function cancelVoid(): void
    {
        $this->voidingId = null;
        $this->voidReason = '';
        $this->resetErrorBag();
    }

    public function confirmVoid(): void
    {
        abort_unless(Gate::allows('payment.manage'), 403);

        $this->validate(['voidReason' => ['required', 'string', 'min:5', 'max:500']]);

        $payment = Payment::where('trip_id', $this->trip->id)->findOrFail($this->voidingId);

        try {
            $this->service()->void($payment, $this->voidReason);
        } catch (\DomainException $e) {
            $this->addError('voidReason', $e->getMessage());

            return;
        }

        $this->trip->refresh();
        $this->cancelVoid();
        session()->flash('status', 'Pembayaran dibatalkan. Status trip: '.(Trip::STATUS[$this->trip->status] ?? $this->trip->status).'.');
    }

    private function plain(string $value): string
    {
        return str_contains($value, '.') ? rtrim(rtrim($value, '0'), '.') : $value;
    }

    public function render()
    {
        $this->trip->refresh();
        $summary = $this->service()->summary($this->trip);

        $canRecord = Gate::allows('payment.manage')
            && in_array($this->trip->status, Trip::PAYABLE, true)
            && $summary['final'] !== null
            && $summary['outstanding'] !== null
            && (float) $summary['outstanding'] > 0;

        return view('livewire.trip.payments', [
            'summary' => $summary,
            'canManage' => Gate::allows('payment.manage') && in_array($this->trip->status, Trip::PAYABLE, true),
            'canRecord' => $canRecord,
        ]);
    }
}