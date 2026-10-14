<?php

namespace App\Models;

use App\Models\Concerns\HasValidity;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class CostItem extends Model
{
    use SoftDeletes, HasValidity;

    protected $fillable = [
        'category', 'name', 'vendor_id', 'destination_id', 'cost_type',
        'cost_behavior', 'standard_cost', 'percentage', 'base_type',
        'attributes', 'status', 'valid_from', 'valid_until',
    ];

    protected function casts(): array
    {
        return [
            'standard_cost' => 'decimal:2',
            'percentage' => 'decimal:4',
            'attributes' => 'array',
            'valid_from' => 'date',
            'valid_until' => 'date',
        ];
    }

    public function vendor() { return $this->belongsTo(Vendor::class); }
    public function destination() { return $this->belongsTo(Destination::class); }
}