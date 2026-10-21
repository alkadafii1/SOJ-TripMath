<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TripPricing extends Model
{
    protected $table = 'trip_pricing';

    protected $fillable = [
        'trip_id', 'pricing_rule_id', 'pricing_mode', 'pricing_method', 'rate_percent',
        'rounding_rule', 'min_margin_threshold', 'block_below_min', 'require_approval',
        'price_per_pax', 'recommended_price', 'quoted_price', 'customer_requested_price',
        'final_price', 'customer_discount', 'below_min_reason',
        'approval_status', 'approved_by', 'approved_at',
        'payment_terms', 'quote_valid_until', 'terms',
    ];

    protected function casts(): array
    {
        return [
            'rate_percent' => 'decimal:4',
            'min_margin_threshold' => 'decimal:4',
            'block_below_min' => 'boolean',
            'require_approval' => 'boolean',
            'price_per_pax' => 'decimal:2',
            'recommended_price' => 'decimal:2',
            'quoted_price' => 'decimal:2',
            'customer_requested_price' => 'decimal:2',
            'final_price' => 'decimal:2',
            'customer_discount' => 'decimal:2',
            'approved_at' => 'datetime',
            'quote_valid_until' => 'date',
        ];
    }

    public function trip() { return $this->belongsTo(Trip::class); }
    public function rule() { return $this->belongsTo(PricingRule::class, 'pricing_rule_id'); }
    public function approver() { return $this->belongsTo(User::class, 'approved_by'); }
}