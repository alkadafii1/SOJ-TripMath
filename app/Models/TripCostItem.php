<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class TripCostItem extends Model
{
    protected $fillable = [
        'trip_id', 'item_type', 'item_id', 'vendor_id', 'category', 'item_name',
        'cost_type', 'cost_behavior', 'quantity', 'duration_qty', 'unit_capacity', 'day_no', 'follows_trip_duration',
        'people_count', 'calculated_quantity', 'override_quantity', 'override_reason',
        'overridden_by', 'overridden_at', 'percentage', 'base_type',
        'is_required', 'is_additional',
        'standard_cost', 'negotiated_cost', 'actual_cost',
        'notes', 'voided_at',
    ];

    protected function casts(): array
    {
        return [
            'follows_trip_duration' => 'boolean',
            'day_no' => 'integer',
            'standard_cost' => 'decimal:2',
            'negotiated_cost' => 'decimal:2',
            'actual_cost' => 'decimal:2',
            'is_required' => 'boolean',
            'is_additional' => 'boolean',
            'overridden_at' => 'datetime',
            'voided_at' => 'datetime',
        ];
    }

    /** Item yang belum di-void. */
    public function scopeActive(Builder $query): Builder
    {
        return $query->whereNull('voided_at');
    }

    public function trip()
    {
        return $this->belongsTo(Trip::class);
    }

    public function item()
    {
        return $this->morphTo('item', 'item_type', 'item_id')->withTrashed();
    }

    /** Bentuk array yang dimakan calculation engine. */
    public function toCalcArray(): array
    {
        return [
            'category' => $this->category,
            'cost_type' => $this->cost_type,
            'cost_behavior' => $this->cost_behavior,
            'quantity' => $this->quantity,
            'duration_qty' => $this->duration_qty,
            'unit_capacity' => $this->unit_capacity,
            'people_count' => $this->people_count,
            'override_quantity' => $this->override_quantity,
            'percentage' => $this->percentage,
            'base_type' => $this->base_type,
            'standard_cost' => $this->standard_cost,
            'negotiated_cost' => $this->negotiated_cost,
            'actual_cost' => $this->actual_cost,
        ];
    }
}
