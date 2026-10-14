<?php

namespace App\Models;

use App\Models\Concerns\HasValidity;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Vehicle extends Model
{
    use SoftDeletes, HasValidity;

    protected $fillable = [
        'vendor_id', 'type', 'capacity', 'standard_cost', 'cost_type',
        'driver_included', 'fuel_included', 'notes',
        'status', 'valid_from', 'valid_until',
    ];

    protected function casts(): array
    {
        return [
            'standard_cost' => 'decimal:2',
            'capacity' => 'integer',
            'driver_included' => 'boolean',
            'fuel_included' => 'boolean',
            'valid_from' => 'date',
            'valid_until' => 'date',
        ];
    }

    public function vendor() { return $this->belongsTo(Vendor::class); }
}