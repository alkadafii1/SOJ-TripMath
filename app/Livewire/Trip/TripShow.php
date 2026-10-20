<?php

namespace App\Livewire\Trip;

use App\Models\Trip;
use App\Services\AuditLogger;
use App\Services\TripCostService;
use App\Services\TripItemFactory;
use App\Support\Options;
use App\Models\PackageVersionItem;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Livewire\Component;

class TripShow extends Component
{
    public Trip $trip;

    public array $itemForm = ['item_type' => '', 'item_id' => '', 'quantity' => 1];

    public ?int $editingId = null;
    public array $edit = [
        'quantity' => 1, 'duration_qty' => null, 'override_quantity' => null,
        'override_reason' => '', 'is_required' => true, 'notes' => null,
    ];

    public bool $showCancel = false;
    public string $cancelReason = '';

    public function mount(Trip $trip): void
    {
        abort_unless(Gate::allows('trip.view'), 403);
        abort_unless(Trip::visibleTo(Auth::user())->whereKey($trip->id)->exists(), 403);

        $this->trip = $trip;
    }

    public function updatedItemForm($value, $key): void
    {
        if ($key === 'item_type') {
            $this->itemForm['item_id'] = '';
        }
    }

    /** Hanya trip berstatus Draft/Estimated yang boleh diubah. */
    protected function editable(): bool
    {
        abort_unless(Gate::allows('trip.update'), 403);

        $this->trip->refresh();
        if (! $this->trip->isEditable()) {
            session()->flash('error', 'Trip berstatus '.(Trip::STATUS[$this->trip->status] ?? $this->trip->status).' tidak bisa diubah lagi.');
            return false;
        }

        return true;
    }

    public function addItem(TripCostService $costs): void
    {
        if (! $this->editable()) {
            return;
        }

        $this->validate([
            'itemForm.item_type' => ['required', 'in:'.Options::keys(Options::ITEM_TYPES)],
            'itemForm.item_id' => ['required', 'integer'],
            'itemForm.quantity' => ['required', 'numeric', 'gt:0'],
        ]);

        $class = Relation::getMorphedModel($this->itemForm['item_type']);
        $master = $class ? $class::active()->find($this->itemForm['item_id']) : null;
        if (! $master) {
            $this->addError('itemForm.item_id', 'Komponen tidak ditemukan atau tidak aktif.');
            return;
        }

        DB::transaction(function () use ($master, $costs) {
            $attributes = TripItemFactory::make($this->itemForm['item_type'], $master, $this->trip);
            $item = $this->trip->costItems()->create($attributes + ['quantity' => $this->itemForm['quantity']]);

            $costs->recalculate($this->trip);

            AuditLogger::record('trip_cost_item', $item->id, 'created', null, [
                'trip_id' => $this->trip->id,
                'item_name' => $item->item_name,
                'standard_cost' => $item->standard_cost,
            ]);
        });

        $this->itemForm = ['item_type' => '', 'item_id' => '', 'quantity' => 1];
    }

    public function startEdit(int $id): void
    {
        $item = $this->trip->costItems()->active()->findOrFail($id);

        $this->editingId = $item->id;
        $this->edit = [
            'quantity' => $this->trimNumber($item->quantity),
            'duration_qty' => $this->trimNumber($item->duration_qty),
            'override_quantity' => $this->trimNumber($item->override_quantity),
            'override_reason' => $item->override_reason ?? '',
            'is_required' => (bool) $item->is_required,
            'notes' => $item->notes,
        ];
        $this->resetErrorBag();
    }

    public function closeEdit(): void
    {
        $this->editingId = null;
        $this->resetErrorBag();
    }

    public function saveEdit(TripCostService $costs): void
    {
        if (! $this->editable()) {
            return;
        }

        $item = $this->trip->costItems()->active()->findOrFail($this->editingId);

        $this->validate([
            'edit.quantity' => ['required', 'numeric', 'gt:0'],
            'edit.duration_qty' => ['nullable', 'numeric', 'min:0'],
            'edit.override_quantity' => ['nullable', 'integer', 'min:1'],
            'edit.override_reason' => ['nullable', 'string', 'max:500'],
            'edit.is_required' => ['boolean'],
            'edit.notes' => ['nullable', 'string', 'max:500'],
        ]);

        // Override hanya untuk item yang jumlahnya dihitung dari kapasitas (kamar, kendaraan)
        $override = $this->blank($this->edit['override_quantity']) || ! $item->unit_capacity
            ? null
            : $this->edit['override_quantity'];
        $reason = trim((string) ($this->edit['override_reason'] ?? ''));

        if ($override !== null && $reason === '') {
            $this->addError('edit.override_reason', 'Alasan override wajib diisi.');
            return;
        }

        $new = [
            'quantity' => $this->edit['quantity'],
            'duration_qty' => in_array($item->cost_type, ['per_room', 'per_night', 'per_day'], true)
                ? ($this->blank($this->edit['duration_qty']) ? null : $this->edit['duration_qty'])
                : $item->duration_qty,
            'override_quantity' => $override,
            'override_reason' => $override !== null ? $reason : null,
            'is_required' => (bool) $this->edit['is_required'],
            'notes' => $this->blank($this->edit['notes']) ? null : $this->edit['notes'],
        ];

        $old = [];
        $changes = [];
        foreach ($new as $field => $value) {
            if (! $this->same($item->{$field}, $value)) {
                $old[$field] = $item->{$field};
                $changes[$field] = $value;
            }
        }

        if (! $changes) {
            $this->closeEdit();
            return;
        }

        $payload = $changes;
        if ($override !== null && (isset($changes['override_quantity']) || isset($changes['override_reason']))) {
            $payload['overridden_by'] = Auth::id();
            $payload['overridden_at'] = now();
        } elseif ($override === null && $item->override_quantity !== null) {
            $payload['overridden_by'] = null;
            $payload['overridden_at'] = null;
        }

        DB::transaction(function () use ($item, $payload, $old, $changes, $override, $reason, $costs) {
            $item->update($payload);
            $result = $costs->recalculate($this->trip);

            $extra = $override !== null
                ? ['calculated_quantity' => $result['lines'][$item->id]['calculated_quantity'] ?? null]
                : [];

            AuditLogger::record('trip_cost_item', $item->id, 'updated', $old, $changes + $extra, $override !== null ? $reason : null);
        });

        $this->closeEdit();
    }

    public function removeItem(int $id, TripCostService $costs): void
    {
        if (! $this->editable()) {
            return;
        }

        $item = $this->trip->costItems()->active()->findOrFail($id);

        DB::transaction(function () use ($item, $costs) {
            $item->update(['voided_at' => now()]); // void, bukan hapus permanen
            $costs->recalculate($this->trip);
            AuditLogger::record('trip_cost_item', $item->id, 'voided', ['item_name' => $item->item_name], null);
        });
    }

    public function changeStatus(string $to): void
    {
        abort_unless(Gate::allows('trip.update'), 403);
        $this->trip->refresh();
        $from = $this->trip->status;

        // Sprint 4 baru mengelola Draft <-> Estimated. Status lain menyusul di sprint berikutnya.
        if (! in_array($to, ['draft', 'estimated'], true) || ! $this->trip->canTransitionTo($to)) {
            session()->flash('error', 'Perubahan status itu tidak diizinkan.');
            return;
        }
        if ($to === 'estimated' && ! $this->trip->costItems()->active()->exists()) {
            session()->flash('error', 'Tambahkan minimal satu komponen biaya sebelum status Estimated.');
            return;
        }

        DB::transaction(function () use ($from, $to) {
            $this->trip->update(['status' => $to]);
            AuditLogger::record('trip', $this->trip->id, 'status_changed', ['status' => $from], ['status' => $to]);
        });
    }

    public function cancelTrip(): void
    {
        abort_unless(Gate::allows('trip.cancel'), 403);

        $this->validate(['cancelReason' => ['required', 'string', 'min:5', 'max:1000']]);

        $this->trip->refresh();
        if (! $this->trip->canTransitionTo('cancelled')) {
            session()->flash('error', 'Trip dengan status ini tidak bisa dibatalkan.');
            return;
        }

        $from = $this->trip->status;
        DB::transaction(function () use ($from) {
            $this->trip->update(['status' => 'cancelled', 'cancel_reason' => $this->cancelReason]);
            AuditLogger::record('trip', $this->trip->id, 'status_changed',
                ['status' => $from], ['status' => 'cancelled'], $this->cancelReason);
        });

        $this->cancelReason = '';
        $this->showCancel = false;
    }

    protected function itemChoices(): array
    {
        $type = $this->itemForm['item_type'];
        $class = $type ? Relation::getMorphedModel($type) : null;

        if (! $class) {
            return [];
        }

        return $class::active()->get()
            ->mapWithKeys(fn ($m) => [$m->id => PackageVersionItem::labelFor($type, $m)])
            ->all();
    }

    // ---- pembantu kecil ----
    private function blank($v): bool
    {
        return $v === null || $v === '';
    }

    private function trimNumber($v): ?string
    {
        if ($v === null) {
            return null;
        }
        $v = (string) $v;

        return str_contains($v, '.') ? rtrim(rtrim($v, '0'), '.') : $v;
    }

    private function same($a, $b): bool
    {
        if ($this->blank($a) || $this->blank($b)) {
            return $this->blank($a) && $this->blank($b);
        }
        if (is_numeric($a) && is_numeric($b)) {
            return bccomp((string) $a, (string) $b, 4) === 0;
        }

        return (string) $a === (string) $b;
    }

    public function render()
    {
        $showMoney = Gate::allows('view-costs');

        return view('livewire.trip.show', [
            'items' => $this->trip->costItems()->active()->orderBy('category')->orderBy('id')->get(),
            'summary' => $showMoney ? app(TripCostService::class)->summary($this->trip) : null,
            'showMoney' => $showMoney,
            'editable' => Gate::allows('trip.update') && $this->trip->isEditable(),
            'canCancel' => Gate::allows('trip.cancel') && $this->trip->canTransitionTo('cancelled'),
            'choices' => $this->itemChoices(),
            'destinations' => $this->trip->destinations()->pluck('name')->all(),
        ]);
    }
}