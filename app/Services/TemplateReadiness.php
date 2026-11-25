<?php

namespace App\Services;

use App\Models\PackageVersion;
use App\Models\PackageVersionItem;
use App\Models\Trip;
use App\Support\Options;
use Illuminate\Validation\ValidationException;

class TemplateReadiness
{
    public function validateServicePeriod(PackageVersionItem $row, ?int $days, ?int $nights, string $type, string $errorKey = 'packageVersionId'): void
    {
        if ($days === null) {
            return;
        }
        $nights ??= max($days - 1, 0);
        $length = in_array($type, TripItemFactory::DURATION_TYPES, true) ? ($row->duration_qty ?? 1) : 1;
        $lastDay = $row->day_no + $length - 1;
        $nightLimit = $days - ($nights < $days ? 1 : 0);
        if ($length < 1 || $row->day_no < 1 || $lastDay > $days
            || (in_array($type, ['per_room', 'per_night'], true) && $row->duration_qty !== null
                && ($length > $nights || $lastDay > $nightLimit))) {
            throw ValidationException::withMessages([$errorKey => 'Hari/durasi layanan melebihi rencana hari dan malam.']);
        }
    }

    public function validatePlan(PackageVersion $version, bool $booking = false): void
    {
        foreach ($version->itinerary_days ?? [] as $index => $day) {
            if ($version->duration_days !== null && $day['day_no'] > $version->duration_days) {
                throw ValidationException::withMessages([$booking ? 'packageVersionId' : "versionForm.itinerary_days.{$index}.day_no" => 'Hari itinerary melebihi durasi paket.']);
            }
        }
        foreach ($version->items()->with('item')->get() as $row) {
            if ($row->item) {
                $info = TripItemFactory::templateInfo($row->item_type, $row->item);
                $this->validateServicePeriod($row, $version->duration_days, $version->nights, $info['cost_type'], $booking ? 'packageVersionId' : 'versionForm.duration_days');
            }
        }
    }

    public function validate(PackageVersion $version, ?Trip $trip = null): void
    {
        $fail = fn (string $message) => throw ValidationException::withMessages(['packageVersionId' => $message]);
        if (! $version->package || $version->package->status !== 'active') {
            $fail('Paket template tidak aktif atau sudah dihapus.');
        }
        if ($trip && ($version->status !== 'active' || ! $version->effective_date || $version->effective_date->gt($trip->start_date))) {
            $fail('Versi template belum aktif/berlaku pada tanggal mulai trip.');
        }
        $this->validatePlan($version, $trip !== null);
        if ($trip) {
            foreach ($version->itinerary_days ?? [] as $day) {
                if ($day['day_no'] > $trip->duration_days) {
                    $fail('Tanggal trip terlalu pendek untuk itinerary template.');
                }
            }
        }
        $rows = $version->items()->with('item')->orderBy('day_no')->get();
        if ($rows->isEmpty()) {
            $fail('Template belum memiliki komponen.');
        }
        foreach ($rows as $row) {
            $master = $row->item;
            $name = $row->item_label;
            if (! $master || $master->trashed() || $master->status !== 'active') {
                $fail("Komponen {$name} tidak tersedia. Perbarui template terlebih dahulu.");
            }
            $info = TripItemFactory::templateInfo($row->item_type, $master);
            $type = $info['cost_type'];
            if (! array_key_exists($type, Options::COST_TYPES)) {
                $fail("Dasar biaya {$name} tidak dikenali.");
            }
            if ($row->day_no < 1 || bccomp((string) $row->quantity, '0', 4) <= 0) {
                $fail("Hari atau jumlah komponen {$name} tidak valid.");
            }
            // The calculator derives units from capacity and participants, not quantity.
            if ($info['automatic_quantity']
                && bccomp((string) $row->quantity, '1', 4) !== 0) {
                $fail("Jumlah {$name} harus 1 karena dihitung otomatis. Tambahkan baris terpisah untuk layanan berulang.");
            }
            if (in_array($type, TripItemFactory::DURATION_TYPES, true) && $row->duration_qty === null && $row->day_no !== 1) {
                $fail("Isi durasi layanan {$name}; durasi seluruh trip hanya untuk layanan mulai hari pertama.");
            }
            if ($trip) {
                $this->validateServicePeriod($row, $trip->duration_days, $trip->nights, $type);
                $end = $trip->start_date->copy()->addDays($row->day_no - 1);
                if ($row->day_no > $trip->duration_days) {
                    $fail("Hari layanan {$name} melebihi tanggal selesai trip.");
                }
                if ($master->valid_from && $end->lt($master->valid_from->startOfDay())) {
                    $fail("Tarif {$name} belum berlaku pada hari layanan.");
                }
                if ($row->duration_qty !== null && in_array($type, TripItemFactory::DURATION_TYPES, true)) {
                    $end->addDays($row->duration_qty - 1);
                } elseif (in_array($type, TripItemFactory::DURATION_TYPES, true)) {
                    $end = $trip->end_date->copy();
                }
                if ($master->valid_until && $end->gt($master->valid_until->endOfDay())) {
                    $fail("Tarif {$name} sudah kedaluwarsa pada periode layanan.");
                }
            }
        }
    }
}
