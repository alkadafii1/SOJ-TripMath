<?php

namespace App\Models\Concerns;

use Illuminate\Database\Eloquent\Builder;

trait HasValidity
{
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', 'active');
    }

    /** Tarif yang berlaku pada tanggal tertentu (kosong = tidak dibatasi). */
    public function scopeValidOn(Builder $query, $date): Builder
    {
        return $query
            ->where(fn ($w) => $w->whereNull('valid_from')->orWhere('valid_from', '<=', $date))
            ->where(fn ($w) => $w->whereNull('valid_until')->orWhere('valid_until', '>=', $date));
    }
}