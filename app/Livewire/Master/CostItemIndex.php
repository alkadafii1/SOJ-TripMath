<?php

namespace App\Livewire\Master;

use App\Models\CostItem;
use App\Models\Destination;
use App\Support\Options;

class CostItemIndex extends MasterCrud
{
    protected function modelClass(): string { return CostItem::class; }
    protected function entity(): string { return 'cost_item'; }
    protected function heading(): string { return 'Biaya Lain'; }
    protected function searchFields(): array { return ['name', 'category']; }
    protected function rateFields(): array { return ['standard_cost', 'percentage']; }
    protected function relations(): array { return ['vendor', 'destination']; }

    protected function fields(): array
    {
        return [
            $this->f('category', 'Kategori', 'select',
                ['required', 'in:'.Options::keys(Options::COST_CATEGORIES)],
                ['options' => Options::COST_CATEGORIES]),
            $this->f('name', 'Nama biaya', 'text', ['required', 'string', 'max:150']),
            $this->vendorField(),
            $this->f('destination_id', 'Destinasi terkait', 'select',
                ['nullable', 'exists:destinations,id'],
                ['options' => Destination::where('status', 'active')->orderBy('name')->pluck('name', 'id')->all()]),
            $this->costTypeField('per_trip'),
            $this->f('cost_behavior', 'Sifat biaya', 'select',
                ['required', 'in:'.Options::keys(Options::COST_BEHAVIOR)],
                ['options' => Options::COST_BEHAVIOR, 'default' => 'variable',
                 'help' => 'Fixed tidak ikut berubah saat pax berubah.']),
            $this->f('standard_cost', 'Tarif standar (Rp)', 'money', ['required', 'numeric', 'min:0']),
            $this->f('percentage', 'Persentase (%)', 'percent', ['nullable', 'numeric', 'min:0', 'max:100'],
                ['help' => 'Hanya untuk tipe biaya Percentage.']),
            $this->f('base_type', 'Dasar persentase', 'text', ['nullable', 'string', 'max:50'],
                ['help' => 'Contoh: revenue.']),
            ...$this->validityFields(),
            $this->statusField(),
        ];
    }

    protected function columns(): array
    {
        return [
            ['key' => 'category', 'label' => 'Kategori'],
            ['key' => 'name', 'label' => 'Nama'],
            ['key' => 'cost_type', 'label' => 'Tipe'],
            ['key' => 'cost_behavior', 'label' => 'Sifat'],
            ['key' => 'standard_cost', 'label' => 'Tarif', 'type' => 'money'],
            ['key' => 'status', 'label' => 'Status', 'type' => 'status'],
        ];
    }
}