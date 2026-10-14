<?php

namespace App\Models;

use App\Models\Concerns\HasValidity;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Destination extends Model
{
    use SoftDeletes, HasValidity;

    protected $fillable = [
        'name', 'category', 'location', 'standard_cost',
        'status', 'valid_from', 'valid_until',
    ];

    protected function casts(): array
    {
        return [
            'standard_cost' => 'decimal:2',
            'valid_from' => 'date',
            'valid_until' => 'date',
        ];
    }

    public function costItems() { return $this->hasMany(CostItem::class); }
}