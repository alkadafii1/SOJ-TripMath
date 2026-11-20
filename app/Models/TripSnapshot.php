<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Foto keuangan trip saat direkonsiliasi. Tidak boleh diubah atau dihapus dari aplikasi,
 * supaya perubahan tarif atau aturan di masa depan tidak mengubah data historis.
 */
class TripSnapshot extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'trip_id', 'revenue', 'standard_cost', 'negotiated_cost', 'actual_cost',
        'vendor_saving', 'customer_discount', 'actual_profit',
        'marketing_percent', 'marketing_base', 'marketing_allocation', 'distributable_profit',
        'owner_percent', 'owner_share', 'partner_percent', 'partner_share', 'profit_rule_set',
        'outstanding_at_reconcile', 'reconcile_note', 'reconciled_by', 'reconciled_at',
    ];

    protected $casts = [
        'revenue' => 'decimal:2',
        'standard_cost' => 'decimal:2',
        'negotiated_cost' => 'decimal:2',
        'actual_cost' => 'decimal:2',
        'vendor_saving' => 'decimal:2',
        'customer_discount' => 'decimal:2',
        'actual_profit' => 'decimal:2',
        'marketing_percent' => 'decimal:4',
        'marketing_base' => 'string',
        'marketing_allocation' => 'decimal:2',
        'distributable_profit' => 'decimal:2',
        'owner_percent' => 'decimal:4',
        'owner_share' => 'decimal:2',
        'partner_percent' => 'decimal:4',
        'partner_share' => 'decimal:2',
        'outstanding_at_reconcile' => 'decimal:2',
        'reconciled_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::updating(fn () => false);  // tidak boleh diubah
        static::deleting(fn () => false);  // tidak boleh dihapus
    }

    public function trip()
    {
        return $this->belongsTo(Trip::class);
    }

    public function reconciler()
    {
        return $this->belongsTo(User::class, 'reconciled_by');
    }
}