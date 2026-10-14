<?php

namespace App\Models;

use App\Models\Concerns\HasValidity;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Meal extends Model
{
    use SoftDeletes, HasValidity;

    protected $fillable = [
        'vendor_id', 'meal_type', 'cost_per_person', 'notes',
        'status', 'valid_from', 'valid_until',
    ];

    protected function casts(): array
    {
        return [
            'cost_per_person' => 'decimal:2',
            'valid_from' => 'date',
            'valid_until' => 'date',
        ];
    }

    public function vendor() { return $this->belongsTo(Vendor::class); }
}