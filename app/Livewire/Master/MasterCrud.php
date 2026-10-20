<?php

namespace App\Livewire\Master;

use App\Models\Vendor;
use App\Services\AuditLogger;
use App\Support\Options;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Livewire\Component;
use Livewire\WithPagination;
use Illuminate\Support\Facades\Gate;

abstract class MasterCrud extends Component
{
    use WithPagination;

    public string $search = '';
    public string $statusFilter = '';
    public bool $showForm = false;
    public ?int $editingId = null;
    public array $form = [];
    public string $reason = '';

    // ---- Diisi oleh setiap entitas ----
    abstract protected function modelClass(): string;
    abstract protected function entity(): string;
    abstract protected function heading(): string;
    abstract protected function fields(): array;
    abstract protected function columns(): array;

    protected function rateFields(): array { return []; }
    protected function searchFields(): array { return ['name']; }
    protected function relations(): array { return []; }
    protected function isInUse(Model $model): bool { return false; }

    // ---- Pembantu penyusun field ----
    protected function f(string $name, string $label, string $type = 'text', array $rules = [], array $extra = []): array
    {
        return array_merge([
            'name' => $name,
            'label' => $label,
            'type' => $type,
            'rules' => $rules,
            'required' => in_array('required', $rules, true),
        ], $extra);
    }

    protected function statusField(): array
    {
        return $this->f('status', 'Status', 'select',
            ['required', 'in:'.Options::keys(Options::STATUS)],
            ['options' => Options::STATUS, 'default' => 'active']);
    }

    protected function notesField(): array
    {
        return $this->f('notes', 'Catatan', 'textarea', ['nullable', 'string', 'max:1000']);
    }

    protected function vendorField(): array
    {
        return $this->f('vendor_id', 'Vendor', 'select', ['nullable', 'exists:vendors,id'], [
            'options' => Vendor::where('status', 'active')->orderBy('name')->pluck('name', 'id')->all(),
        ]);
    }

    protected function costTypeField(string $default): array
    {
        return $this->f('cost_type', 'Tipe biaya', 'select',
            ['required', 'in:'.Options::keys(Options::COST_TYPES)],
            ['options' => Options::COST_TYPES, 'default' => $default]);
    }

    protected function validityFields(): array
    {
        return [
            $this->f('valid_from', 'Berlaku dari', 'date', ['nullable', 'date']),
            $this->f('valid_until', 'Berlaku sampai', 'date', ['nullable', 'date', 'after_or_equal:form.valid_from']),
        ];
    }

    protected function tabs(): array
    {
        return [
            'vendor' => ['master.vendors', 'Vendor'],
            'hotel' => ['master.hotels', 'Hotel'],
            'vehicle' => ['master.vehicles', 'Kendaraan'],
            'destination' => ['master.destinations', 'Destinasi'],
            'activity' => ['master.activities', 'Aktivitas'],
            'meal' => ['master.meals', 'Meal'],
            'cost_item' => ['master.cost-items', 'Biaya Lain'],
        ];
    }

    // ---- Siklus hidup ----
    public function mount(): void
    {
        abort_unless(Gate::allows('master.view'), 403);
    }

    public function updatingSearch(): void { $this->resetPage(); }
    public function updatingStatusFilter(): void { $this->resetPage(); }

    protected function newQuery(): Builder
    {
        $class = $this->modelClass();
        return $class::query();
    }

    protected function manageAbility(): string { return 'master.manage'; }
    protected function detailRoute(): ?string { return null; }

    protected function authorizeManage(): void
    {
        abort_unless(Gate::allows($this->manageAbility()), 403);
    }

    protected function resetForm(): void
    {
        $this->form = collect($this->fields())->mapWithKeys(fn ($f) => [
            $f['name'] => $f['default'] ?? ($f['type'] === 'checkbox' ? false : null),
        ])->all();
        $this->editingId = null;
        $this->reason = '';
        $this->resetErrorBag();
    }

    public function create(): void
    {
        $this->authorizeManage();
        $this->resetForm();
        $this->showForm = true;
    }

    public function edit(int $id): void
    {
        $this->authorizeManage();
        $model = $this->newQuery()->findOrFail($id);

        $this->resetForm();
        $this->editingId = $id;
        foreach ($this->fields() as $f) {
            $value = $model->{$f['name']};
            $this->form[$f['name']] = $value instanceof \DateTimeInterface
                ? $value->format('Y-m-d')
                : $value;
        }
        $this->showForm = true;
    }

    public function closeForm(): void
    {
        $this->showForm = false;
        $this->editingId = null;
        $this->reason = '';
        $this->resetErrorBag();
    }

    protected function buildRules(): array
    {
        $rules = [];
        foreach ($this->fields() as $f) {
            $rules['form.'.$f['name']] = $f['rules'];
        }
        return $rules;
    }

    protected function labels(): array
    {
        $labels = [];
        foreach ($this->fields() as $f) {
            $labels['form.'.$f['name']] = strtolower($f['label']);
        }
        return $labels;
    }

    public function save(): void
    {
        $this->authorizeManage();

        $data = $this->validate($this->buildRules(), [], $this->labels())['form'];
        $data = array_map(fn ($v) => $v === '' ? null : $v, $data);

        if ($this->editingId === null) {
            DB::transaction(function () use ($data) {
                $class = $this->modelClass();
                $model = $class::create($data);
                AuditLogger::record($this->entity(), $model->id, 'created', null, $data);
            });
            $message = 'Data berhasil ditambahkan.';
        } else {
            $model = $this->newQuery()->findOrFail($this->editingId);
            $model->fill($data);
            $dirty = $model->getDirty();

            if (! $dirty) {
                $this->closeForm();
                session()->flash('status', 'Tidak ada perubahan.');
                return;
            }

            $rateChanged = array_intersect(array_keys($dirty), $this->rateFields());
            if ($rateChanged && trim($this->reason) === '') {
                $this->addError('reason', 'Alasan wajib diisi karena tarif berubah.');
                return;
            }

            $old = [];
            foreach (array_keys($dirty) as $key) {
                $old[$key] = $model->getRawOriginal($key);
            }

            DB::transaction(function () use ($model, $old, $dirty) {
                $model->save();
                AuditLogger::record(
                    $this->entity(), $model->id, 'updated',
                    $old, $dirty, trim($this->reason) ?: null
                );
            });
            $message = 'Perubahan berhasil disimpan.';
        }

        $this->closeForm();
        session()->flash('status', $message);
    }

    public function toggleStatus(int $id): void
    {
        $this->authorizeManage();
        $model = $this->newQuery()->findOrFail($id);
        $old = $model->status;
        $new = $old === 'active' ? 'inactive' : 'active';

        DB::transaction(function () use ($model, $old, $new) {
            $model->update(['status' => $new]);
            AuditLogger::record($this->entity(), $model->id, 'status_changed', ['status' => $old], ['status' => $new]);
        });
    }

    public function delete(int $id): void
    {
        $this->authorizeManage();
        $model = $this->newQuery()->findOrFail($id);

        if ($this->isInUse($model)) {
            session()->flash('error', 'Data masih dipakai data lain. Nonaktifkan saja, jangan dihapus.');
            return;
        }

        DB::transaction(function () use ($model) {
            AuditLogger::record($this->entity(), $model->id, 'deleted', $model->toArray());
            $model->delete(); // soft delete
        });
        session()->flash('status', 'Data dihapus.');
    }

    public function render()
    {
        $query = $this->newQuery()->with($this->relations());

        if ($this->search !== '') {
            $query->where(function ($w) {
                foreach ($this->searchFields() as $field) {
                    $w->orWhere($field, 'like', '%'.$this->search.'%');
                }
            });
        }
        if ($this->statusFilter !== '') {
            $query->where('status', $this->statusFilter);
        }

        $rateLabels = collect($this->fields())
            ->whereIn('name', $this->rateFields())
            ->pluck('label')->implode(', ');

        return view('livewire.master.crud', [
            'rows' => $query->orderBy($this->searchFields()[0] ?? 'id')->paginate(10),
            'fields' => $this->fields(),
            'columns' => $this->columns(),
            'heading' => $this->heading(),
            'tabs' => $this->tabs(),
            'currentTab' => $this->entity(),
            'rateLabels' => $rateLabels,
            'canManage' => Gate::allows($this->manageAbility()),
            'detailRoute' => $this->detailRoute(),
        ])->layout('layouts.app');
    }
}