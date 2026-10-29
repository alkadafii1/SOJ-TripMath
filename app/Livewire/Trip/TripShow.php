<?php

namespace App\Livewire\Trip;

use App\Models\PackageVersionItem;
use App\Models\Trip;
use App\Services\AuditLogger;
use App\Services\TripCalculator;
use App\Services\TripCostService;
use App\Services\TripItemFactory;
use App\Support\CostExplain;
use App\Support\Options;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Livewire\Component;

class TripShow extends Component
{
    public Trip $trip;

    public array $itemForm = [
        'item_type' => '', 'item_id' => '', 'cost_type' => '',
        'quantity' => 1, 'people_count' => '', 'duration_qty' => '',
    ];

    public ?int $editingId = null;
    public array $edit = [
        'cost_type' => '', 'quantity' => 1, 'people_count' => null, 'duration_qty' => null,
        'override_quantity' => null, 'override_reason' => '', 'is_required' => true, 'notes' => null,
    ];

    public bool $showCancel = false;
    public string $cancelReason = '';

    public function mount(Trip $trip): void
    {
        abort_unless(Gate::allows('trip.view'), 403);
        abort_unless(Trip::visibleTo(Auth::user())->whereKey($trip->id)->exists(), 403);

        $this->trip = $trip;
    }

    // ------------------------------------------------------------ validasi

    protected function messages(): array
    {
        return [
            'required' => ':attribute wajib diisi.',
            'numeric' => ':attribute harus berupa angka.',
            'integer' => ':attribute harus berupa bilangan bulat.',
            'gt' => ':attribute harus lebih dari 0.',
            'in' => ':attribute tidak valid.',
            'min.numeric' => ':attribute minimal :min.',
            'max.numeric' => ':attribute maksimal :max.',
            'min.string' => ':attribute minimal :min karakter.',
            'max.string' => ':attribute terlalu panjang (maksimal :max karakter).',
        ];
    }

    protected function validationAttributes(): array
    {
        return [
            'itemForm.item_type' => 'jenis komponen',
            'itemForm.item_id' => 'komponen',
            'itemForm.cost_type' => 'tipe biaya',
            'itemForm.quantity' => 'jumlah',
            'itemForm.people_count' => 'jumlah orang',
            'itemForm.duration_qty' => 'durasi',
            'edit.cost_type' => 'tipe biaya',
            'edit.quantity' => 'jumlah',
            'edit.people_count' => 'jumlah orang',
            'edit.duration_qty' => 'durasi',
            'edit.override_quantity' => 'jumlah unit manual',
            'edit.override_reason' => 'alasan',
            'edit.notes' => 'catatan',
            'cancelReason' => 'alasan pembatalan',
        ];
    }

    // ------------------------------------------------- form tambah komponen

    protected function emptyItemForm(): array
    {
        return [
            'item_type' => '', 'item_id' => '', 'cost_type' => '',
            'quantity' => 1, 'people_count' => '', 'duration_qty' => '',
        ];
    }

    public function updatedItemForm($value, $key): void
    {
        if ($key === 'item_type') {
            $this->itemForm = array_merge($this->emptyItemForm(), ['item_type' => $this->itemForm['item_type']]);
        }

        if ($key === 'item_id') {
            $this->applyDefaults();
        }

        if ($key === 'cost_type') {
            $this->itemForm['quantity'] = 1;
            $this->itemForm['people_count'] = '';
            $this->itemForm['duration_qty'] = TripItemFactory::defaultDuration((string) $value, $this->trip);
        }
    }

    /** Setelah komponen dipilih: isi tipe biaya, jumlah, dan durasi bawaan dari master. */
    protected function applyDefaults(): void
    {
        $master = $this->masterFromForm();
        if (! $master) {
            return;
        }

        $base = TripItemFactory::make($this->itemForm['item_type'], $master, $this->trip);
        $this->itemForm['cost_type'] = $base['cost_type'];
        $this->itemForm['quantity'] = 1;
        $this->itemForm['people_count'] = '';
        $this->itemForm['duration_qty'] = $base['duration_qty'];
    }

    protected function masterFromForm(): ?Model
    {
        $type = $this->itemForm['item_type'] ?? '';
        $id = $this->itemForm['item_id'] ?? '';
        if ($type === '' || $id === '' || ! is_numeric($id)) {
            return null;
        }

        $class = Relation::getMorphedModel($type);

        return $class ? $class::active()->find($id) : null;
    }

    /** Tipe Percentage hanya untuk biaya berbasis persentase; tipe lain tidak bisa menjadi Percentage. */
    protected function typeOptions(?string $current): array
    {
        if ($current === 'percentage') {
            return ['percentage' => Options::COST_TYPES['percentage']];
        }

        return array_diff_key(Options::COST_TYPES, ['percentage' => true]);
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
            'itemForm.cost_type' => ['required', 'in:'.Options::keys(Options::COST_TYPES)],
            'itemForm.quantity' => ['nullable', 'numeric', 'gt:0'],
            'itemForm.people_count' => ['nullable', 'integer', 'min:1', 'max:'.max($this->trip->effectivePax(), 1)],
            'itemForm.duration_qty' => ['nullable', 'numeric', 'gt:0'],
        ]);

        $master = $this->masterFromForm();
        if (! $master) {
            $this->addError('itemForm.item_id', 'Komponen tidak ditemukan atau tidak aktif.');
            return;
        }

        $base = TripItemFactory::make($this->itemForm['item_type'], $master, $this->trip);
        $type = $this->itemForm['cost_type'];

        if (($base['cost_type'] === 'percentage') !== ($type === 'percentage')) {
            $this->addError('itemForm.cost_type', 'Tipe Percentage hanya untuk biaya berbasis persentase.');
            return;
        }

        $hasUnits = $base['unit_capacity'] !== null && in_array($type, TripItemFactory::UNIT_TYPES, true);
        $people = ($type === 'per_person' && ! $this->blank($this->itemForm['people_count']))
            ? (int) $this->itemForm['people_count']
            : null;
        $quantity = ($type === 'per_person' || $hasUnits)
            ? '1'
            : ($this->blank($this->itemForm['quantity']) ? '1' : (string) $this->itemForm['quantity']);

        $duration = '1';
        if (in_array($type, TripItemFactory::DURATION_TYPES, true)) {
            $duration = $this->blank($this->itemForm['duration_qty'])
                ? TripItemFactory::defaultDuration($type, $this->trip)
                : (string) $this->itemForm['duration_qty'];

            if ((float) $duration <= 0) {
                $this->addError('itemForm.duration_qty', 'Jumlah '.($type === 'per_day' ? 'hari' : 'malam').' harus lebih dari 0. Cek jumlah malam di data trip.');
                return;
            }
        }

        $sameBefore = $this->trip->costItems()->active()
            ->where('item_type', $base['item_type'])
            ->where('item_id', $base['item_id'])
            ->count();

        DB::transaction(function () use ($base, $type, $people, $quantity, $duration, $hasUnits, $costs) {
            $item = $this->trip->costItems()->create(array_merge($base, [
                'cost_type' => $type,
                'cost_behavior' => TripItemFactory::behaviorFor($type, $hasUnits, $people),
                'quantity' => $quantity,
                'people_count' => $people,
                'duration_qty' => $duration,
            ]));

            $costs->recalculate($this->trip);

            AuditLogger::record('trip_cost_item', $item->id, 'created', null, [
                'trip_id' => $this->trip->id,
                'item_name' => $item->item_name,
                'cost_type' => $item->cost_type,
                'standard_cost' => $item->standard_cost,
            ]);
        });

        $this->itemForm = $this->emptyItemForm();

        if ($sameBefore > 0) {
            session()->flash('warning', 'Komponen yang sama sudah ada di trip ini ('.$sameBefore.' baris). Pastikan ini bukan duplikat.');
        }
    }

    // -------------------------------------------------------- ubah komponen

    public function startEdit(int $id): void
    {
        $item = $this->trip->costItems()->active()->findOrFail($id);

        $this->editingId = $item->id;
        $this->edit = [
            'cost_type' => $item->cost_type,
            'quantity' => $this->trimNumber($item->quantity) ?? 1,
            'people_count' => $item->people_count,
            'duration_qty' => $this->trimNumber($item->duration_qty),
            'override_quantity' => $this->trimNumber($item->override_quantity),
            'override_reason' => $item->override_reason ?? '',
            'is_required' => (bool) $item->is_required,
            'notes' => $item->notes,
        ];
        $this->resetErrorBag();
    }

    public function updatedEdit($value, $key): void
    {
        if ($key === 'cost_type' && $this->editingId) {
            $this->edit['quantity'] = 1;
            $this->edit['people_count'] = null;
            $this->edit['override_quantity'] = null;
            $this->edit['override_reason'] = '';
            $this->edit['duration_qty'] = in_array($value, TripItemFactory::DURATION_TYPES, true)
                ? TripItemFactory::defaultDuration((string) $value, $this->trip)
                : null;
        }
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
            'edit.cost_type' => ['required', 'in:'.Options::keys(Options::COST_TYPES)],
            'edit.quantity' => ['nullable', 'numeric', 'gt:0'],
            'edit.people_count' => ['nullable', 'integer', 'min:1', 'max:'.max($this->trip->effectivePax(), 1)],
            'edit.duration_qty' => ['nullable', 'numeric', 'gt:0'],
            'edit.override_quantity' => ['nullable', 'integer', 'min:1'],
            'edit.override_reason' => ['nullable', 'string', 'max:500'],
            'edit.is_required' => ['boolean'],
            'edit.notes' => ['nullable', 'string', 'max:500'],
        ]);

        $type = $this->edit['cost_type'];

        if (($item->cost_type === 'percentage') !== ($type === 'percentage')) {
            $this->addError('edit.cost_type', 'Tipe Percentage tidak bisa diganti atau dipilih untuk komponen ini.');
            return;
        }

        $hasUnits = $item->unit_capacity && in_array($type, TripItemFactory::UNIT_TYPES, true);
        $people = ($type === 'per_person' && ! $this->blank($this->edit['people_count']))
            ? (int) $this->edit['people_count']
            : null;
        $quantity = ($type === 'per_person' || $hasUnits)
            ? '1'
            : ($this->blank($this->edit['quantity']) ? '1' : (string) $this->edit['quantity']);

        $duration = '1';
        if (in_array($type, TripItemFactory::DURATION_TYPES, true)) {
            $duration = $this->blank($this->edit['duration_qty'])
                ? TripItemFactory::defaultDuration($type, $this->trip)
                : (string) $this->edit['duration_qty'];

            if ((float) $duration <= 0) {
                $this->addError('edit.duration_qty', 'Jumlah '.($type === 'per_day' ? 'hari' : 'malam').' harus lebih dari 0. Cek jumlah malam di data trip.');
                return;
            }
        }

        // Override jumlah unit hanya untuk item yang unitnya dihitung dari kapasitas
        $override = ($hasUnits && ! $this->blank($this->edit['override_quantity']))
            ? (int) $this->edit['override_quantity']
            : null;
        $reason = trim((string) ($this->edit['override_reason'] ?? ''));

        if ($override !== null && $reason === '') {
            $this->addError('edit.override_reason', 'Alasan override wajib diisi.');
            return;
        }

        $new = [
            'cost_type' => $type,
            'cost_behavior' => TripItemFactory::behaviorFor($type, (bool) $hasUnits, $people),
            'quantity' => $quantity,
            'people_count' => $people,
            'duration_qty' => $duration,
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

    // ---------------------------------------------------------- status trip

    public function changeStatus(string $to): void
    {
        abort_unless(Gate::allows('trip.update'), 403);
        $this->trip->refresh();
        $from = $this->trip->status;

        // Draft <-> Estimated dikelola di sini. Status lain lewat halaman harga dan sprint berikutnya.
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

    // ------------------------------------------------------ data untuk tampilan

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

    /** Info form tambah: tipe, kapasitas, jumlah unit otomatis, dan perkiraan subtotal. */
    protected function formInfo(): ?array
    {
        $master = $this->masterFromForm();
        if (! $master) {
            return null;
        }

        $base = TripItemFactory::make($this->itemForm['item_type'], $master, $this->trip);
        $type = $this->itemForm['cost_type'] !== '' ? $this->itemForm['cost_type'] : $base['cost_type'];
        $pax = $this->trip->effectivePax();
        $hasUnits = $base['unit_capacity'] !== null && in_array($type, TripItemFactory::UNIT_TYPES, true);

        $info = [
            'base_type' => $base['cost_type'],
            'type' => $type,
            'capacity' => $base['unit_capacity'],
            'has_cap' => $hasUnits,
            'auto_units' => $hasUnits
                ? app(TripCalculator::class)->calculateRoomRequirement($pax, (int) $base['unit_capacity'])
                : null,
            'preview' => null,
        ];

        if ($type === 'percentage' || ! array_key_exists($type, Options::COST_TYPES)) {
            return $info;
        }

        $people = ($type === 'per_person' && ! $this->blank($this->itemForm['people_count']) && is_numeric($this->itemForm['people_count']))
            ? (int) $this->itemForm['people_count']
            : null;
        $quantity = $this->positive($this->itemForm['quantity']) ?? '1';
        $duration = in_array($type, TripItemFactory::DURATION_TYPES, true)
            ? ($this->positive($this->itemForm['duration_qty']) ?? TripItemFactory::defaultDuration($type, $this->trip))
            : '1';

        $line = app(TripCalculator::class)->calculateTripCost($pax, ['x' => [
            'category' => $base['category'], 'cost_type' => $type, 'cost_behavior' => 'variable',
            'quantity' => $quantity, 'duration_qty' => $duration,
            'unit_capacity' => $base['unit_capacity'], 'override_quantity' => null,
            'people_count' => $people, 'percentage' => null, 'base_type' => null,
            'standard_cost' => $base['standard_cost'], 'negotiated_cost' => null, 'actual_cost' => null,
        ]])['lines']['x'];

        $info['preview'] = [
            'amount' => $line['amount'],
            'text' => CostExplain::line($type, [
                'rate' => $line['rate'],
                'units' => $line['units'] ?? $quantity,
                'duration' => $duration,
                'people' => $people ?? $pax,
                'quantity' => $quantity,
            ], true),
        ];

        return $info;
    }

    // ---------------------------------------------------------- pembantu kecil

    private function blank($v): bool
    {
        return $v === null || $v === '';
    }

    private function positive($v): ?string
    {
        return (is_numeric($v) && (float) $v > 0) ? (string) $v : null;
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
        $items = $this->trip->costItems()->active()->orderBy('category')->orderBy('id')->get();
        $formInfo = $this->formInfo();

        return view('livewire.trip.show', [
            'items' => $items,
            'summary' => $showMoney ? app(TripCostService::class)->summary($this->trip) : null,
            'showMoney' => $showMoney,
            'editable' => Gate::allows('trip.update') && $this->trip->isEditable(),
            'canCancel' => Gate::allows('trip.cancel') && $this->trip->canTransitionTo('cancelled'),
            'choices' => $this->itemChoices(),
            'destinations' => $this->trip->destinations()->pluck('name')->all(),
            'formInfo' => $formInfo,
            'addTypeOptions' => $formInfo ? $this->typeOptions($formInfo['base_type']) : [],
            'dupes' => $items->groupBy(fn ($i) => $i->item_type.'#'.$i->item_id)->map(fn ($g) => $g->count())->all(),
        ]);
    }
}   