<?php

namespace App\Livewire\Package;

use App\Livewire\Master\MasterCrud;
use App\Models\Package;
use App\Support\Options;
use Illuminate\Database\Eloquent\Model;

class PackageIndex extends MasterCrud
{
    protected function modelClass(): string
    {
        return Package::class;
    }

    protected function entity(): string
    {
        return 'package';
    }

    protected function heading(): string
    {
        return 'Template Paket Wisata';
    }

    protected function searchFields(): array
    {
        return ['name', 'trip_type'];
    }

    protected function manageAbility(): string
    {
        return 'package.manage';
    }

    protected function detailRoute(): ?string
    {
        return 'packages.show';
    }

    protected function relations(): array
    {
        return ['latestVersion.items.item'];
    }

    protected function notice(): ?string
    {
        return 'Paket adalah rancangan layanan, bukan harga jual final. Buka detail untuk menyusun itinerary dan layanan, lalu gunakan versi aktif untuk membuat trip customer.';
    }

    protected function tabs(): array
    {
        return [];
    }

    protected function fields(): array
    {
        return [
            $this->f('name', 'Nama paket', 'text', ['required', 'string', 'max:200']),
            $this->f('trip_type', 'Tipe trip', 'select',
                ['required', 'in:'.Options::keys(Options::TRIP_TYPES)],
                ['options' => Options::TRIP_TYPES]),
            $this->f('description', 'Deskripsi', 'textarea', ['nullable', 'string', 'max:2000'], ['help' => 'Ringkasan produk perjalanan. Durasi, itinerary, dan layanan diatur pada detail template.']),
            $this->statusField(),
        ];
    }

    protected function columns(): array
    {
        return [
            ['key' => 'name', 'label' => 'Paket'],
            ['key' => 'trip_type_label', 'label' => 'Tipe'],
            ['key' => 'template_summary', 'label' => 'Rencana terbaru'],
            ['key' => 'template_destinations', 'label' => 'Destinasi'],
            ['key' => 'status', 'label' => 'Status', 'type' => 'status'],
        ];
    }

    protected function isInUse(Model $model): bool
    {
        return $model->versions()->exists();
    }
}
