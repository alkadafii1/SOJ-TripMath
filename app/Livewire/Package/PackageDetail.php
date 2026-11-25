<?php

namespace App\Livewire\Package;

use App\Livewire\Concerns\LoadsActiveMasters;
use App\Models\Package;
use App\Models\PackageVersion;
use App\Models\PackageVersionItem;
use App\Services\AuditLogger;
use App\Services\TemplateReadiness;
use App\Services\TripItemFactory;
use App\Support\Options;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Livewire\Component;

class PackageDetail extends Component
{
    use LoadsActiveMasters;

    public Package $package;

    public ?int $editingItemId = null;

    public ?int $versionId = null;

    public array $versionForm = [
        'effective_date' => null, 'itinerary' => null,
        'inclusions' => null, 'exclusions' => null,
        'duration_days' => null, 'nights' => null, 'itinerary_days' => [],
    ];

    public array $itemForm = [
        'item_type' => '', 'item_id' => '', 'quantity' => 1, 'day_no' => 1, 'duration_qty' => 1, 'notes' => null,
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
            'duration_days' => $version->duration_days,
            'nights' => $version->nights,
            'itinerary_days' => $version->itinerary_days ?? [],
        ];
        $this->resetItemForm();
        $this->resetErrorBag();
    }

    protected function resetItemForm(): void
    {
        $this->editingItemId = null;
        $this->itemForm = [
            'item_type' => '', 'item_id' => '', 'quantity' => 1, 'day_no' => 1, 'duration_qty' => 1, 'notes' => null,
        ];
    }

    public function updatedItemForm($value, $key): void
    {
        if ($key === 'item_type') {
            $this->itemForm['item_id'] = '';
        }
        if (in_array($key, ['item_type', 'item_id'], true)) {
            $this->itemForm['quantity'] = 1;
            $this->itemForm['duration_qty'] = 1;
            $this->resetErrorBag();
        }
    }

    protected function selectedMaster()
    {
        $type = $this->itemForm['item_type'] ?? '';
        $id = $this->itemForm['item_id'] ?? null;
        return is_string($type) && filter_var($id, FILTER_VALIDATE_INT)
            ? $this->activeMasters($type, withVendor: true)->find((int) $id)
            : null;
    }

    public function addItineraryDay(): void
    {
        if (! $this->editableVersion()) {
            return;
        }
        if (count($this->versionForm['itinerary_days']) >= 30) {
            $this->addError('versionForm.itinerary_days', 'Maksimal 30 hari itinerary.');

            return;
        }
        $this->versionForm['itinerary_days'][] = [
            'day_no' => (collect($this->versionForm['itinerary_days'])->max('day_no') ?? 0) + 1, 'title' => '', 'description' => '',
        ];
    }

    public function removeItineraryDay(int $index): void
    {
        if (! $this->editableVersion()) {
            return;
        }
        unset($this->versionForm['itinerary_days'][$index]);
        $this->versionForm['itinerary_days'] = array_values($this->versionForm['itinerary_days']);
        $this->resetErrorBag();
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
                'duration_days' => $latest?->duration_days,
                'nights' => $latest?->nights,
                'itinerary_days' => $latest?->itinerary_days,
                'change_log' => $latest ? trim($this->changeReason) : 'Versi awal',
            ]);

            foreach ($latest?->items ?? [] as $item) {
                $version->items()->create(
                    $item->only(['item_type', 'item_id', 'quantity', 'day_no', 'duration_qty', 'notes'])
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
            'versionForm.duration_days' => ['nullable', 'integer', 'min:1', 'max:30'],
            'versionForm.nights' => ['nullable', 'integer', 'min:0', 'max:30', 'lte:versionForm.duration_days'],
            'versionForm.itinerary_days' => ['array', 'max:30'],
            'versionForm.itinerary_days.*' => ['array:day_no,title,description'],
            'versionForm.itinerary_days.*.day_no' => ['required', 'integer', 'min:1', 'max:30', 'distinct'],
            'versionForm.itinerary_days.*.title' => ['required', 'string', 'max:150'],
            'versionForm.itinerary_days.*.description' => ['required', 'string', 'max:2000'],
            'versionForm.itinerary' => ['nullable', 'string', 'max:10000'],
            'versionForm.inclusions' => ['nullable', 'string', 'max:5000'],
            'versionForm.exclusions' => ['nullable', 'string', 'max:5000'],
        ])['versionForm'];

        $data['duration_days'] = ($data['duration_days'] ?? '') === '' ? null : $data['duration_days'];
        $data['nights'] = ($data['nights'] ?? '') === '' ? null : $data['nights'];
        $version->fill($data);
        app(TemplateReadiness::class)->validatePlan($version);
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

        $master = $this->selectedMaster();
        $info = $master ? TripItemFactory::templateInfo($this->itemForm['item_type'], $master) : null;
        if ($info && $info['automatic_quantity']) {
            $this->itemForm['quantity'] = 1;
        }
        if ($info && ! $info['uses_duration']) {
            $this->itemForm['duration_qty'] = null;
        }
        $data = $this->validate([
            'itemForm.item_type' => ['required', 'in:'.Options::keys(Options::ITEM_TYPES)],
            'itemForm.item_id' => ['required', 'integer'],
            'itemForm.quantity' => ['required', 'numeric', 'gt:0', 'max:9999'],
            'itemForm.duration_qty' => ['nullable', 'integer', 'min:1', 'max:60'],
            'itemForm.day_no' => ['required', 'integer', 'min:1', 'max:30'],
            'itemForm.notes' => ['nullable', 'string', 'max:500'],
        ])['itemForm'];
        $data['duration_qty'] = ($data['duration_qty'] ?? '') === '' ? null : $data['duration_qty'];

        if (! $master) {
            $this->addError('itemForm.item_id', 'Item tidak ditemukan atau tidak aktif.');

            return;
        }

        $costType = $info['cost_type'];
        if (! $info['uses_duration']) {
            $data['duration_qty'] = null;
        } elseif ($data['duration_qty'] === null && (int) $data['day_no'] !== 1) {
            $this->addError('itemForm.duration_qty', 'Layanan mulai setelah hari pertama harus memiliki durasi sendiri.');

            return;
        }
        $candidate = new PackageVersionItem($data);
        app(TemplateReadiness::class)->validateServicePeriod($candidate, $version->duration_days, $version->nights, $costType, 'itemForm.day_no');

        DB::transaction(function () use ($version, $data) {
            $item = $this->editingItemId ? $version->items()->findOrFail($this->editingItemId) : new PackageVersionItem;
            $old = $item->exists ? $item->toArray() : null;
            $item->fill($data);
            $version->items()->save($item);
            AuditLogger::record('package_version_item', $item->id, $old ? 'updated' : 'created', $old,
                $item->toArray());
        });

        $this->resetItemForm();
    }

    public function editItem(int $id): void
    {
        $version = $this->editableVersion();
        if (! $version) {
            return;
        }
        $item = $version->items()->findOrFail($id);
        $this->editingItemId = $id;
        $this->itemForm = $item->only(['item_type', 'item_id', 'quantity', 'day_no', 'duration_qty', 'notes']);
        $this->resetErrorBag();
    }

    public function cancelItemEdit(): void
    {
        $this->resetItemForm();
        $this->resetErrorBag();
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
        if ($this->editingItemId === $id) {
            $this->resetItemForm();
        }
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

        $this->saveVersion();
        $version->refresh();
        app(TemplateReadiness::class)->validate($version);

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
        $type = $this->itemForm['item_type'] ?? '';
        return $this->activeMasters(is_string($type) ? $type : '', withVendor: true)
            ->mapWithKeys(function ($master) use ($type) {
                $vendor = method_exists($master, 'vendor') ? $master->vendor?->name : null;

                return [$master->id => PackageVersionItem::labelFor($type, $master).($vendor ? ' - '.$vendor : '')];
            })
            ->all();
    }

    public function render()
    {
        $versions = $this->package->versions()->orderByDesc('version_no')->get();
        $version = $versions->firstWhere('id', $this->versionId);
        $vendorRelations = [];
        foreach (Relation::morphMap() as $class) {
            if (method_exists(new $class, 'vendor')) {
                $vendorRelations[$class] = ['vendor'];
            }
        }

        return view('livewire.package.detail', [
            'versions' => $versions,
            'version' => $version,
            'items' => $version
                ? $version->items()->with(['item' => fn (MorphTo $morph) => $morph->morphWith($vendorRelations)])->orderBy('day_no')->orderBy('id')->get()
                : collect(),
            'canManage' => Gate::allows('package.manage'),
            'editable' => $version && $version->status === 'draft' && Gate::allows('package.manage'),
            'hasDraft' => $versions->contains('status', 'draft'),
            'choices' => $this->itemChoices(),
            'serviceInfo' => ($master = $this->selectedMaster()) ? TripItemFactory::templateInfo($this->itemForm['item_type'], $master) : null,
            'showMoney' => Gate::allows('view-costs'),
        ]);
    }
}
