<?php

namespace App\Livewire\Master;

use App\Models\Vendor;
use App\Support\Options;
use Illuminate\Database\Eloquent\Model;

class VendorIndex extends MasterCrud
{
    protected function modelClass(): string { return Vendor::class; }
    protected function entity(): string { return 'vendor'; }
    protected function heading(): string { return 'Vendor'; }
    protected function searchFields(): array { return ['name', 'category', 'contact']; }

    protected function fields(): array
    {
        return [
            $this->f('name', 'Nama vendor', 'text', ['required', 'string', 'max:150']),
            $this->f('category', 'Kategori', 'select',
                ['nullable', 'in:'.Options::keys(Options::VENDOR_CATEGORIES)],
                ['options' => Options::VENDOR_CATEGORIES]),
            $this->f('contact', 'Kontak', 'text', ['nullable', 'string', 'max:150']),
            $this->statusField(),
            $this->notesField(),
        ];
    }

    protected function columns(): array
    {
        return [
            ['key' => 'name', 'label' => 'Nama'],
            ['key' => 'category', 'label' => 'Kategori'],
            ['key' => 'contact', 'label' => 'Kontak'],
            ['key' => 'status', 'label' => 'Status', 'type' => 'status'],
        ];
    }

    protected function isInUse(Model $model): bool
    {
        return $model->hotels()->exists() || $model->vehicles()->exists()
            || $model->activities()->exists() || $model->meals()->exists()
            || $model->costItems()->exists();
    }
}