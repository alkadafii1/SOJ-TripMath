<?php

namespace App\Livewire\Master;

use App\Models\Hotel;

class HotelIndex extends MasterCrud
{
    protected function modelClass(): string { return Hotel::class; }
    protected function entity(): string { return 'hotel'; }
    protected function heading(): string { return 'Hotel & Tipe Kamar'; }
    protected function searchFields(): array { return ['name', 'room_type']; }
    protected function rateFields(): array { return ['standard_rate', 'extra_bed_cost']; }
    protected function relations(): array { return ['vendor']; }

    protected function fields(): array
    {
        return [
            $this->f('name', 'Nama hotel', 'text', ['required', 'string', 'max:150']),
            $this->f('room_type', 'Tipe kamar', 'text', ['required', 'string', 'max:100']),
            $this->vendorField(),
            $this->costTypeField('per_room'),
            $this->f('standard_rate', 'Tarif standar (Rp)', 'money', ['required', 'numeric', 'min:0']),
            $this->f('extra_bed_cost', 'Biaya extra bed (Rp)', 'money', ['nullable', 'numeric', 'min:0']),
            $this->f('occupancy', 'Kapasitas kamar (orang)', 'number', ['required', 'integer', 'min:1'], ['default' => 2]),
            $this->f('max_pax', 'Maksimum pax per kamar', 'number', ['nullable', 'integer', 'min:1']),
            $this->f('breakfast_included', 'Termasuk sarapan', 'checkbox', ['boolean']),
            ...$this->validityFields(),
            $this->statusField(),
            $this->notesField(),
        ];
    }

    protected function columns(): array
    {
        return [
            ['key' => 'name', 'label' => 'Hotel'],
            ['key' => 'room_type', 'label' => 'Tipe kamar'],
            ['key' => 'vendor.name', 'label' => 'Vendor'],
            ['key' => 'standard_rate', 'label' => 'Tarif', 'type' => 'money'],
            ['key' => 'occupancy', 'label' => 'Kapasitas'],
            ['key' => 'valid_until', 'label' => 'Berlaku sampai', 'type' => 'date'],
            ['key' => 'status', 'label' => 'Status', 'type' => 'status'],
        ];
    }
}