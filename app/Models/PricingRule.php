<?php

namespace App\Models;

use App\Support\Options;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class PricingRule extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'method', 'rate_percent', 'rounding_rule', 'min_margin_threshold',
        'block_below_min', 'require_approval', 'effective_from', 'effective_to', 'status',
    ];

    protected function casts(): array
    {
        return [
            'rate_percent' => 'decimal:4',
            'min_margin_threshold' => 'decimal:4',
            'block_below_min' => 'boolean',
            'require_approval' => 'boolean',
            'effective_from' => 'date',
            'effective_to' => 'date',
        ];
    }

    /** Aturan yang aktif dan berlaku pada tanggal tertentu. */
    public function scopeEffectiveOn(Builder $query, string $date): Builder
    {
        return $query->where('status', 'active')
            ->where(fn ($w) => $w->whereNull('effective_from')->orWhere('effective_from', '<=', $date))
            ->where(fn ($w) => $w->whereNull('effective_to')->orWhere('effective_to', '>=', $date));
    }

    public function getMethodLabelAttribute(): string
    {
        return Options::PRICING_METHODS[$this->method] ?? $this->method;
    }

    public function getRoundingLabelAttribute(): string
    {
        return Options::ROUNDING_RULES[$this->rounding_rule] ?? $this->rounding_rule;
    }
}