<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProfitRule extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'rule_set', 'rule_type', 'party', 'percentage', 'calculation_base',
        'effective_from', 'effective_to', 'status',
    ];

    protected function casts(): array
    {
        return [
            'percentage' => 'decimal:4',
            'effective_from' => 'date',
            'effective_to' => 'date',
            'created_at' => 'datetime',
        ];
    }
}
