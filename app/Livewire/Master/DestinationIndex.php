<?php

namespace App\Livewire\Master;

use App\Models\Destination;
use Illuminate\Database\Eloquent\Model;

class DestinationIndex extends MasterCrud
{
    protected function modelClass(): string { return Destination::class; }
    protected function entity(): string { return 'destination'; }
    protected function heading(): string { return 'Destinasi'; }
    protected function searchFields(): array { return ['name', 'category', 'location']; }
    protected function rateFields(): array { return ['standard_cost']; }

    protected function fields(): array
    {
        return [
            $this->f('name', 'Nama destinasi', 'text', ['required', 'string', 'max:150']),
            $this->f('category', 'Kategori', 'text', ['nullable', 'string', 'max:100'],
                ['help' => 'Contoh: gunung, pantai, taman nasional, wisata budaya.']),
            $this->f('location', 'Lokasi', 'text', ['nullable', 'string', 'max:150']),
            $this->f('standard_cost', 'Biaya tiket/standar (Rp)', 'money', ['required', 'numeric', 'min:0']),
            ...$this->validityFields(),
            $this->statusField(),
        ];
    }

    protected function columns(): array
    {
        return [
            ['key' => 'name', 'label' => 'Destinasi'],
            ['key' => 'category', 'label' => 'Kategori'],
            ['key' => 'location', 'label' => 'Lokasi'],
            ['key' => 'standard_cost', 'label' => 'Biaya', 'type' => 'money'],
            ['key' => 'valid_until', 'label' => 'Berlaku sampai', 'type' => 'date'],
            ['key' => 'status', 'label' => 'Status', 'type' => 'status'],
        ];
    }

    protected function isInUse(Model $model): bool
    {
        return $model->costItems()->exists();
    }
}