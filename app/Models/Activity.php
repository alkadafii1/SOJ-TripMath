<?php

namespace App\Models;

use App\Models\Concerns\HasValidity;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Activity extends Model
{
    use SoftDeletes, HasValidity;

    protected $fillable = [
        'vendor_id', 'name', 'standard_cost', 'cost_type', 'capacity',
        'duration_hours', 'notes', 'status', 'valid_from', 'valid_until',
    ];

    protected function casts(): array
    {
        return [
            'standard_cost' => 'decimal:2',
            'capacity' => 'integer',
            'duration_hours' => 'integer',
            'valid_from' => 'date',
            'valid_until' => 'date',
        ];
    }

    public function vendor() { return $this->belongsTo(Vendor::class); }
}