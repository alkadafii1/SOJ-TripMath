<?php

namespace App\Livewire\Master;

use App\Models\Activity;

class ActivityIndex extends MasterCrud
{
    protected function modelClass(): string { return Activity::class; }
    protected function entity(): string { return 'activity'; }
    protected function heading(): string { return 'Aktivitas'; }
    protected function rateFields(): array { return ['standard_cost']; }
    protected function relations(): array { return ['vendor']; }

    protected function fields(): array
    {
        return [
            $this->f('name', 'Nama aktivitas', 'text', ['required', 'string', 'max:150']),
            $this->vendorField(),
            $this->costTypeField('per_person'),
            $this->f('standard_cost', 'Tarif standar (Rp)', 'money', ['required', 'numeric', 'min:0']),
            $this->f('capacity', 'Kapasitas', 'number', ['nullable', 'integer', 'min:1']),
            $this->f('duration_hours', 'Durasi (jam)', 'number', ['nullable', 'integer', 'min:1']),
            ...$this->validityFields(),
            $this->statusField(),
            $this->notesField(),
        ];
    }

    protected function columns(): array
    {
        return [
            ['key' => 'name', 'label' => 'Aktivitas'],
            ['key' => 'vendor.name', 'label' => 'Vendor'],
            ['key' => 'standard_cost', 'label' => 'Tarif', 'type' => 'money'],
            ['key' => 'capacity', 'label' => 'Kapasitas'],
            ['key' => 'duration_hours', 'label' => 'Durasi (jam)'],
            ['key' => 'status', 'label' => 'Status', 'type' => 'status'],
        ];
    }
}