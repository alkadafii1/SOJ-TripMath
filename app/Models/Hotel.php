<?php

namespace App\Models;

use App\Models\Concerns\HasValidity;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Hotel extends Model
{
    use SoftDeletes, HasValidity;

    protected $fillable = [
        'vendor_id', 'name', 'room_type', 'standard_rate', 'occupancy', 'max_pax',
        'extra_bed_cost', 'breakfast_included', 'cost_type', 'notes',
        'status', 'valid_from', 'valid_until',
    ];

    protected function casts(): array
    {
        return [
            'standard_rate' => 'decimal:2',
            'extra_bed_cost' => 'decimal:2',
            'occupancy' => 'integer',
            'max_pax' => 'integer',
            'breakfast_included' => 'boolean',
            'valid_from' => 'date',
            'valid_until' => 'date',
        ];
    }

    public function vendor() { return $this->belongsTo(Vendor::class); }
}