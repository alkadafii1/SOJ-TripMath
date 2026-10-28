<?php

namespace App\Livewire\Trip;

use App\Models\Destination;
use App\Models\PackageVersion;
use App\Models\Trip;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\TripCostService;
use App\Services\TripService;
use App\Support\Options;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Livewire\Component;


class TripForm extends Component
{
    public ?Trip $trip = null;
    public ?int $packageVersionId = null;
    public array $destinationIds = [];
    public array $form = [];

    public function mount(?Trip $trip = null): void
    {
        if ($trip && $trip->exists) {
            abort_unless(Gate::allows('trip.update'), 403);
            abort_unless(Trip::visibleTo(Auth::user())->whereKey($trip->id)->exists(), 403);
            abort_unless($trip->isEditable(), 403);

            $this->trip = $trip;
            $this->form = [
                'trip_name' => $trip->trip_name,
                'customer_name' => $trip->customer_name,
                'customer_contact' => $trip->customer_contact,
                'trip_type' => $trip->trip_type,
                'start_date' => $trip->start_date->format('Y-m-d'),
                'end_date' => $trip->end_date->format('Y-m-d'),
                'pax' => $trip->pax,
                // a. Tambah entri nights di mode ubah
                'nights' => $trip->nights === max($trip->duration_days - 1, 0) ? null : $trip->nights,
                'min_pax' => $trip->min_pax,
                'max_capacity' => $trip->max_capacity,
                'confirmed_pax' => $trip->confirmed_pax,
                'assigned_staff_id' => $trip->assigned_staff_id,
                'assigned_partner_id' => $trip->assigned_partner_id,
                'notes' => $trip->notes,
            ];
            $this->destinationIds = $trip->destinations()->pluck('destinations.id')
                ->map(fn ($id) => (string) $id)->all();

            return;
        }

        abort_unless(Gate::allows('trip.create'), 403);

        $this->form = [
            'trip_name' => null, 'customer_name' => null, 'customer_contact' => null,
            'trip_type' => 'private', 'start_date' => null, 'end_date' => null,
            'pax' => 2, 
            // b. Tambah nights di mode baru
            'nights' => null, 
            'min_pax' => null, 'max_capacity' => null, 'confirmed_pax' => null,
            'assigned_staff_id' => null, 'assigned_partner_id' => null, 'notes' => null,
        ];
    }

    protected function formRules(): array
    {
        return [
            'form.trip_name' => ['required', 'string', 'max:200'],
            'form.customer_name' => ['required', 'string', 'max:150'],
            'form.customer_contact' => ['nullable', 'string', 'max:100'],
            'form.trip_type' => ['required', 'in:'.Options::keys(Options::TRIP_TYPES)],
            'form.start_date' => ['required', 'date'],
            'form.end_date' => ['required', 'date', 'after_or_equal:form.start_date'],
            'form.pax' => ['required', 'integer', 'min:1', 'max:500'],
            // c. Tambah aturan validasi nights
            'form.nights' => ['nullable', 'integer', 'min:0', 'max:60'],
            'form.min_pax' => ['nullable', 'integer', 'min:1'],
            'form.max_capacity' => ['nullable', 'integer', 'min:1'],
            'form.confirmed_pax' => ['nullable', 'integer', 'min:0'],
            'form.assigned_staff_id' => ['nullable', 'exists:users,id'],
            'form.assigned_partner_id' => ['nullable', 'exists:users,id'],
            'form.notes' => ['nullable', 'string', 'max:2000'],
            'destinationIds' => ['array'],
            'destinationIds.*' => ['integer', 'exists:destinations,id'],
            'packageVersionId' => ['nullable', 'exists:package_versions,id'],
        ];
    }

    public function save(TripService $trips, TripCostService $costs)
    {
        $isNew = $this->trip === null;
        abort_unless(Gate::allows($isNew ? 'trip.create' : 'trip.update'), 403);

        if (! $isNew) {
            $this->trip->refresh();
            abort_unless($this->trip->isEditable(), 403);
        }

        $validated = $this->validate($this->formRules());
        $data = array_map(fn ($v) => $v === '' ? null : $v, $validated['form']);

        // Kolom Open Trip hanya berlaku untuk tipe Open Trip
        if ($data['trip_type'] !== 'open_trip') {
            $data['min_pax'] = $data['max_capacity'] = $data['confirmed_pax'] = null;
        }
        if ($data['max_capacity'] !== null && (int) $data['pax'] > (int) $data['max_capacity']) {
            $this->addError('form.pax', 'Pax melebihi kapasitas maksimum.');
            return;
        }
        if ($data['min_pax'] !== null && $data['max_capacity'] !== null && (int) $data['min_pax'] > (int) $data['max_capacity']) {
            $this->addError('form.min_pax', 'Minimum pax melebihi kapasitas maksimum.');
            return;
        }
        if ($data['confirmed_pax'] !== null && $data['max_capacity'] !== null && (int) $data['confirmed_pax'] > (int) $data['max_capacity']) {
            $this->addError('form.confirmed_pax', 'Confirmed pax melebihi kapasitas maksimum.');
            return;
        }

        $duration = Trip::daysBetween($data['start_date'], $data['end_date']);
        
        // d. Logika Penentuan Jumlah Malam (Nights)
        $nights = ($data['nights'] ?? null) !== null ? (int) $data['nights'] : max($duration - 1, 0);
        if ($nights > $duration) {
            $this->addError('form.nights', 'Jumlah malam tidak boleh lebih banyak dari jumlah hari ('.$duration.').');
            return;
        }
        $data['nights'] = $nights;

        $destinationIds = array_map('intval', $this->destinationIds);

        if ($isNew) {
            $version = null;
            if ($this->packageVersionId) {
                $version = PackageVersion::where('status', 'active')->find($this->packageVersionId);
                if (! $version) {
                    $this->addError('packageVersionId', 'Versi paket tidak aktif.');
                    return;
                }
            }

            $trip = DB::transaction(function () use ($data, $duration, $version, $destinationIds, $trips, $costs) {
                $trip = Trip::create($data + [
                    'trip_code' => $trips->nextCode(),
                    'duration_days' => $duration,
                    'status' => 'draft',
                    'package_version_id' => $version?->id,
                    'itinerary' => $version?->itinerary,
                    'inclusions' => $version?->inclusions,
                    'exclusions' => $version?->exclusions,
                    'created_by' => Auth::id(),
                ]);

                if ($version) {
                    $trips->snapshotFromVersion($trip, $version);
                    $destinationIds = array_merge($destinationIds, $trips->destinationIdsFromVersion($version));
                }
                $trips->syncDestinations($trip, $destinationIds);
                $costs->recalculate($trip);

                AuditLogger::record('trip', $trip->id, 'created', null, [
                    'trip_code' => $trip->trip_code,
                    'pax' => $trip->pax,
                    'package_version_id' => $trip->package_version_id,
                ]);

                return $trip;
            });

            session()->flash('status', "Trip {$trip->trip_code} dibuat.");
        } else {
            $trip = $this->trip;
            $trip->fill($data + ['duration_days' => $duration]);

            $dirty = $trip->getDirty();
            $old = [];
            foreach (array_keys($dirty) as $key) {
                $old[$key] = $trip->getRawOriginal($key);
            }
            
            // e. Perubahan nights juga memicu hitung ulang durasi komponen hotel
            $durationChanged = array_key_exists('duration_days', $dirty) || array_key_exists('nights', $dirty);

            DB::transaction(function () use ($trip, $dirty, $old, $durationChanged, $destinationIds, $trips, $costs) {
                $trip->save();
                $trips->syncDestinations($trip, $destinationIds);

                if ($durationChanged) {
                    $costs->refreshDurations($trip);
                }
                $costs->recalculate($trip);

                if ($dirty) {
                    AuditLogger::record('trip', $trip->id, 'updated', $old, $dirty);
                }
            });

            session()->flash('status', 'Trip diperbarui.'.($durationChanged
                ? ' Durasi berubah, jumlah malam/hari pada biaya yang bergantung durasi ikut dihitung ulang.'
                : ''));
        }

        return $this->redirectRoute('trips.show', ['trip' => $trip->id], navigate: true);
    }

    public function render()
    {
        return view('livewire.trip.form', [
            'staffOptions' => User::role('staff')->where('status', 'active')->orderBy('name')->pluck('name', 'id')->all(),
            'partnerOptions' => User::role('partner')->where('status', 'active')->orderBy('name')->pluck('name', 'id')->all(),
            'destinationOptions' => Destination::active()->orderBy('name')->pluck('name', 'id')->all(),
            'versionOptions' => PackageVersion::with('package')->where('status', 'active')->get()
                ->mapWithKeys(fn ($v) => [$v->id => $v->package->name.' (v'.$v->version_no.')'])->all(),
            'isEdit' => $this->trip !== null,
        ]);
    }
}