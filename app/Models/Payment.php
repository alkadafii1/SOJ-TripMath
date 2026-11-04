<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class Payment extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'trip_id', 'payment_type', 'installment_no', 'amount', 'paid_at',
        'method', 'reference', 'notes', 'voided_at', 'void_reason',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'paid_at' => 'date',
            'voided_at' => 'datetime',
            'created_at' => 'datetime',
        ];
    }

    /** Pembayaran yang belum dibatalkan (void). */
    public function scopeActive(Builder $query): Builder
    {
        return $query->whereNull('voided_at');
    }

    public function trip()
    {
        return $this->belongsTo(Trip::class);
    }
}