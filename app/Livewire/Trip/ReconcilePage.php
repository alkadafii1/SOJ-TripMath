<?php

namespace App\Livewire\Trip;

use App\Models\Trip;
use App\Services\ReconciliationService;
use App\Support\Options;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Livewire\Component;

class ReconcilePage extends Component
{
    public Trip $trip;

    /** [id komponen => biaya aktual] */
    public array $actual = [];
    public string $actualNote = '';

    public array $extra = ['category' => '', 'name' => '', 'amount' => '', 'notes' => ''];

    public bool $reviewed = false;
    public string $note = '';

    public ?int $voidingId = null;
    public string $voidReason = '';

    public function mount(Trip $trip): void
    {
        abort_unless(Gate::allows('trip.view') && Gate::allows('view-costs'), 403);
        abort_unless(Trip::visibleTo(Auth::user())->whereKey($trip->id)->exists(), 403);

        $this->trip = $trip;
        $this->loadActual();
    }

    protected function messages(): array
    {
        return [
            'required' => ':attribute wajib diisi.',
            'numeric' => ':attribute harus berupa angka.',
            'in' => ':attribute tidak valid.',
            'min.numeric' => ':attribute tidak boleh negatif.',
            'max.string' => ':attribute terlalu panjang (maksimal :max karakter).',
            'min.string' => ':attribute minimal :min karakter.',
            'decimal' => ':attribute maksimal dua angka di belakang koma.',
        ];
    }

    protected function validationAttributes(): array
    {
        return [
            'actual.*' => 'biaya aktual',
            'actualNote' => 'alasan perubahan',
            'extra.category' => 'kategori',
            'extra.name' => 'nama biaya',
            'extra.amount' => 'jumlah',
            'extra.notes' => 'catatan',
            'note' => 'alasan rekonsiliasi',
            'voidReason' => 'alasan pembatalan',
        ];
    }

    protected function service(): ReconciliationService
    {
        return app(ReconciliationService::class);
    }

    protected function loadActual(): void
    {
        $this->actual = $this->service()->items($this->trip)
            ->mapWithKeys(fn ($i) => [$i->id => $this->plain($i->actual_cost)])
            ->all();
    }

    protected function plain($v): string
    {
        if ($v === null || $v === '') {
            return '';
        }
        $v = (string) $v;

        return str_contains($v, '.') ? rtrim(rtrim($v, '0'), '.') : $v;
    }

    // ------------------------------------------------------------ biaya aktual

    public function saveActual(): void
    {
        abort_unless(Gate::allows('trip.update'), 403);

        $this->validate([
            'actual.*' => ['nullable', 'numeric', 'min:0', 'decimal:0,2'],
            'actualNote' => ['nullable', 'string', 'max:500'],
        ]);

        try {
            $changed = $this->service()->recordActualCosts($this->trip, $this->actual, $this->actualNote);
        } catch (\DomainException $e) {
            $this->addError('actualNote', $e->getMessage());

            return;
        }

        $this->actualNote = '';
        $this->loadActual();
        session()->flash('status', $changed ? "{$changed} biaya aktual disimpan." : 'Tidak ada perubahan.');
    }

    public function addExtra(): void
    {
        abort_unless(Gate::allows('trip.update'), 403);

        $data = $this->validate([
            'extra.category' => ['required', 'in:'.Options::keys(Options::COST_CATEGORIES)],
            'extra.name' => ['required', 'string', 'max:150'],
            'extra.amount' => ['required', 'numeric', 'min:0', 'decimal:0,2'],
            'extra.notes' => ['nullable', 'string', 'max:500'],
        ])['extra'];

        try {
            $this->service()->addAdditionalCost(
                $this->trip,
                $data['category'],
                trim($data['name']),
                (string) $data['amount'],
                null,
                trim((string) ($data['notes'] ?? '')) ?: null,
            );
        } catch (\DomainException $e) {
            $this->addError('extra.amount', $e->getMessage());

            return;
        }

        $this->extra = ['category' => '', 'name' => '', 'amount' => '', 'notes' => ''];
        $this->loadActual();
        session()->flash('status', 'Biaya tambahan dicatat.');
    }

    public function startVoid(int $id): void
    {
        abort_unless(Gate::allows('trip.update'), 403);

        $this->voidingId = $this->trip->costItems()->active()->where('is_additional', true)->findOrFail($id)->id;
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
        abort_unless(Gate::allows('trip.update'), 403);

        $this->validate(['voidReason' => ['required', 'string', 'min:5', 'max:500']]);

        try {
            $this->service()->voidAdditionalCost($this->trip, (int) $this->voidingId, $this->voidReason);
        } catch (\DomainException $e) {
            $this->addError('voidReason', $e->getMessage());

            return;
        }

        $this->cancelVoid();
        $this->loadActual();
        session()->flash('status', 'Biaya tambahan dibatalkan.');
    }

    // ------------------------------------------------------------ rekonsiliasi

    public function reconcile(): void
    {
        abort_unless(Gate::allows('trip.reconcile'), 403);

        $this->validate(['note' => ['nullable', 'string', 'max:1000']]);

        try {
            $this->service()->reconcile($this->trip, $this->reviewed, $this->note);
        } catch (\DomainException $e) {
            session()->flash('error', $e->getMessage());

            return;
        }

        $this->trip->refresh();
        $this->reviewed = false;
        $this->note = '';
        $this->loadActual();
        session()->flash('status', 'Trip berhasil direkonsiliasi. Snapshot keuangan tersimpan dan trip terkunci.');
    }

    public function render()
    {
        $this->trip->refresh();
        $service = $this->service();
        $figures = $service->figures($this->trip);
        $checklist = $service->checklist($this->trip, $figures);

        return view('livewire.trip.reconcile', [
            'items' => $service->items($this->trip),
            'figures' => $figures,
            'checklist' => $checklist,
            'checklistOk' => collect($checklist)->every(fn ($c) => $c['ok']),
            'snapshot' => $this->trip->snapshot()->first(),
            'categories' => Options::COST_CATEGORIES,
            'canEditActual' => Gate::allows('trip.update') && in_array($this->trip->status, Trip::ACTUAL_COST_OPEN, true),
            'canReconcile' => Gate::allows('trip.reconcile') && $this->trip->status === 'completed',
            'showFinance' => Gate::allows('view-finance'),
        ]);
    }
}