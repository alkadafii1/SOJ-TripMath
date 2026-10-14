<?php

namespace App\Livewire\Master;

use App\Models\Meal;
use App\Support\Options;

class MealIndex extends MasterCrud
{
    protected function modelClass(): string { return Meal::class; }
    protected function entity(): string { return 'meal'; }
    protected function heading(): string { return 'Meal'; }
    protected function searchFields(): array { return ['meal_type']; }
    protected function rateFields(): array { return ['cost_per_person']; }
    protected function relations(): array { return ['vendor']; }

    protected function fields(): array
    {
        return [
            $this->f('meal_type', 'Jenis meal', 'select',
                ['required', 'in:'.Options::keys(Options::MEAL_TYPES)],
                ['options' => Options::MEAL_TYPES]),
            $this->vendorField(),
            $this->f('cost_per_person', 'Biaya per orang (Rp)', 'money', ['required', 'numeric', 'min:0']),
            ...$this->validityFields(),
            $this->statusField(),
            $this->notesField(),
        ];
    }

    protected function columns(): array
    {
        return [
            ['key' => 'meal_type', 'label' => 'Jenis'],
            ['key' => 'vendor.name', 'label' => 'Vendor/Restoran'],
            ['key' => 'cost_per_person', 'label' => 'Biaya/orang', 'type' => 'money'],
            ['key' => 'valid_until', 'label' => 'Berlaku sampai', 'type' => 'date'],
            ['key' => 'status', 'label' => 'Status', 'type' => 'status'],
        ];
    }
}