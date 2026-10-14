<?php

namespace App\Livewire\Master;

use App\Models\Vehicle;

class VehicleIndex extends MasterCrud
{
    protected function modelClass(): string { return Vehicle::class; }
    protected function entity(): string { return 'vehicle'; }
    protected function heading(): string { return 'Kendaraan'; }
    protected function searchFields(): array { return ['type']; }
    protected function rateFields(): array { return ['standard_cost']; }
    protected function relations(): array { return ['vendor']; }

    protected function fields(): array
    {
        return [
            $this->f('type', 'Jenis kendaraan', 'text', ['required', 'string', 'max:100']),
            $this->vendorField(),
            $this->f('capacity', 'Kapasitas (penumpang)', 'number', ['required', 'integer', 'min:1']),
            $this->costTypeField('per_day'),
            $this->f('standard_cost', 'Tarif standar (Rp)', 'money', ['required', 'numeric', 'min:0']),
            $this->f('driver_included', 'Termasuk sopir', 'checkbox', ['boolean'], ['default' => true]),
            $this->f('fuel_included', 'Termasuk BBM', 'checkbox', ['boolean']),
            ...$this->validityFields(),
            $this->statusField(),
            $this->notesField(),
        ];
    }

    protected function columns(): array
    {
        return [
            ['key' => 'type', 'label' => 'Jenis'],
            ['key' => 'vendor.name', 'label' => 'Vendor'],
            ['key' => 'capacity', 'label' => 'Kapasitas'],
            ['key' => 'standard_cost', 'label' => 'Tarif', 'type' => 'money'],
            ['key' => 'driver_included', 'label' => 'Sopir', 'type' => 'bool'],
            ['key' => 'fuel_included', 'label' => 'BBM', 'type' => 'bool'],
            ['key' => 'status', 'label' => 'Status', 'type' => 'status'],
        ];
    }
}