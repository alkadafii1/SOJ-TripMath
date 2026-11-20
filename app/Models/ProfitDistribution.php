<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Bagian laba tiap pihak untuk satu trip. Dibuat saat rekonsiliasi dan tidak boleh diubah atau dihapus.
 */
class ProfitDistribution extends Model
{
    protected $table = 'profit_distribution';

    public $timestamps = false;

    protected $fillable = ['trip_id', 'profit_rule_id', 'party', 'percentage', 'amount'];

    protected function casts(): array
    {
        return [
            'percentage' => 'decimal:4',
            'amount' => 'decimal:2',
            'created_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::updating(fn () => false);
        static::deleting(fn () => false);
    }

    public function trip()
    {
        return $this->belongsTo(Trip::class);
    }

    public function rule()
    {
        return $this->belongsTo(ProfitRule::class, 'profit_rule_id');
    }
}
