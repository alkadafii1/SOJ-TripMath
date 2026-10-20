<?php

namespace App\Livewire\Package;

use App\Models\Package;
use App\Models\PackageVersion;
use App\Models\PackageVersionItem;
use App\Services\AuditLogger;
use App\Support\Options;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Livewire\Component;

class PackageDetail extends Component
{
    public Package $package;
    public ?int $versionId = null;

    public array $versionForm = [
        'effective_date' => null, 'itinerary' => null,
        'inclusions' => null, 'exclusions' => null,
    ];

    public array $itemForm = [
        'item_type' => '', 'item_id' => '', 'quantity' => 1, 'day_no' => 1, 'notes' => null,
    ];

    public string $changeReason = '';

    public function mount(Package $package): void
    {
        abort_unless(Gate::allows('master.view'), 403);
        $this->package = $package;

        $latest = $package->versions()->orderByDesc('version_no')->first();
        if ($latest) {
            $this->selectVersion($latest->id);
        }
    }

    public function selectVersion(int $id): void
    {
        $version = $this->package->versions()->findOrFail($id);
        $this->versionId = $version->id;
        $this->versionForm = [
            'effective_date' => $version->effective_date?->format('Y-m-d'),
            'itinerary' => $version->itinerary,
            'inclusions' => $version->inclusions,
            'exclusions' => $version->exclusions,
        ];
        $this->resetItemForm();
        $this->resetErrorBag();
    }

    protected function resetItemForm(): void
    {
        $this->itemForm = [
            'item_type' => '', 'item_id' => '', 'quantity' => 1, 'day_no' => 1, 'notes' => null,
        ];
    }

    public function updatedItemForm($value, $key): void
    {
        if ($key === 'item_type') {
            $this->itemForm['item_id'] = '';
        }
    }

    protected function version(): ?PackageVersion
    {
        return $this->versionId ? $this->package->versions()->find($this->versionId) : null;
    }

    /** Hanya versi draft yang boleh diubah. Versi aktif/arsip terkunci. */
    protected function editableVersion(): ?PackageVersion
    {
        abort_unless(Gate::allows('package.manage'), 403);

        $version = $this->version();
        if (! $version) {
            return null;
        }
        if ($version->status !== 'draft') {
            session()->flash('error', 'Versi ini sudah dipublikasikan dan terkunci. Buat versi baru untuk mengubahnya.');
            return null;
        }
        return $version;
    }

    public function createVersion(): void
    {
        abort_unless(Gate::allows('package.manage'), 403);

        if ($this->package->versions()->where('status', 'draft')->exists()) {
            session()->flash('error', 'Masih ada versi draft. Selesaikan dan publikasikan dulu.');
            return;
        }

        $latest = $this->package->versions()->with('items')->orderByDesc('version_no')->first();

        if ($latest && trim($this->changeReason) === '') {
            $this->addError('changeReason', 'Alasan perubahan wajib diisi untuk versi baru.');
            return;
        }

        $new = DB::transaction(function () use ($latest) {
            $version = $this->package->versions()->create([
                'version_no' => ($latest?->version_no ?? 0) + 1,
                'effective_date' => now()->toDateString(),
                'status' => 'draft',
                'itinerary' => $latest?->itinerary,
                'inclusions' => $latest?->inclusions,
                'exclusions' => $latest?->exclusions,
                'change_log' => $latest ? trim($this->changeReason) : 'Versi awal',
            ]);

            foreach ($latest?->items ?? [] as $item) {
                $version->items()->create(
                    $item->only(['item_type', 'item_id', 'quantity', 'day_no', 'notes'])
                );
            }

            AuditLogger::record(
                'package_version', $version->id, 'created', null,
                ['package_id' => $this->package->id, 'version_no' => $version->version_no],
                $latest ? trim($this->changeReason) : null
            );

            return $version;
        });

        $this->changeReason = '';
        $this->selectVersion($new->id);
        session()->flash('status', "Versi {$new->version_no} (draft) dibuat.");
    }

    public function saveVersion(): void
    {
        $version = $this->editableVersion();
        if (! $version) {
            return;
        }

        $data = $this->validate([
            'versionForm.effective_date' => ['required', 'date'],
            'versionForm.itinerary' => ['nullable', 'string', 'max:10000'],
            'versionForm.inclusions' => ['nullable', 'string', 'max:5000'],
            'versionForm.exclusions' => ['nullable', 'string', 'max:5000'],
        ])['versionForm'];

        $version->fill($data);
        $dirty = $version->getDirty();

        if ($dirty) {
            $old = array_intersect_key($version->getRawOriginal(), $dirty);
            DB::transaction(function () use ($version, $old, $dirty) {
                $version->save();
                AuditLogger::record('package_version', $version->id, 'updated', $old, $dirty);
            });
        }

        session()->flash('status', 'Versi disimpan.');
    }

    public function addItem(): void
    {
        $version = $this->editableVersion();
        if (! $version) {
            return;
        }

        $this->validate([
            'itemForm.item_type' => ['required', 'in:'.Options::keys(Options::ITEM_TYPES)],
            'itemForm.item_id' => ['required', 'integer'],
            'itemForm.quantity' => ['required', 'numeric', 'gt:0'],
            'itemForm.day_no' => ['required', 'integer', 'min:1', 'max:30'],
            'itemForm.notes' => ['nullable', 'string', 'max:500'],
        ]);

        $class = Relation::getMorphedModel($this->itemForm['item_type']);
        if (! $class || ! $class::active()->whereKey($this->itemForm['item_id'])->exists()) {
            $this->addError('itemForm.item_id', 'Item tidak ditemukan atau tidak aktif.');
            return;
        }

        DB::transaction(function () use ($version) {
            $item = $version->items()->create($this->itemForm);
            AuditLogger::record('package_version_item', $item->id, 'created', null,
                $item->only(['package_version_id', 'item_type', 'item_id', 'quantity', 'day_no']));
        });

        $this->resetItemForm();
    }

    public function removeItem(int $id): void
    {
        $version = $this->editableVersion();
        if (! $version) {
            return;
        }

        $item = $version->items()->findOrFail($id);

        DB::transaction(function () use ($item) {
            AuditLogger::record('package_version_item', $item->id, 'deleted', $item->toArray());
            $item->delete();
        });
    }

    public function publish(): void
    {
        $version = $this->editableVersion();
        if (! $version) {
            return;
        }

        if (! $version->items()->exists()) {
            session()->flash('error', 'Tambahkan minimal satu komponen sebelum dipublikasikan.');
            return;
        }

        DB::transaction(function () use ($version) {
            $this->package->versions()
                ->where('status', 'active')
                ->where('id', '!=', $version->id)
                ->get()
                ->each(function ($old) {
                    $old->update(['status' => 'archived']);
                    AuditLogger::record('package_version', $old->id, 'status_changed',
                        ['status' => 'active'], ['status' => 'archived']);
                });

            $version->update(['status' => 'active']);
            AuditLogger::record('package_version', $version->id, 'status_changed',
                ['status' => 'draft'], ['status' => 'active']);
        });

        $this->selectVersion($version->id);
        session()->flash('status', "Versi {$version->version_no} dipublikasikan dan terkunci.");
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

    public function render()
    {
        $version = $this->version();

        return view('livewire.package.detail', [
            'versions' => $this->package->versions()->orderByDesc('version_no')->get(),
            'version' => $version,
            'items' => $version
                ? $version->items()->with('item')->orderBy('day_no')->orderBy('id')->get()
                : collect(),
            'canManage' => Gate::allows('package.manage'),
            'editable' => $version && $version->status === 'draft' && Gate::allows('package.manage'),
            'hasDraft' => $this->package->versions()->where('status', 'draft')->exists(),
            'choices' => $this->itemChoices(),
        ]);
    }
}