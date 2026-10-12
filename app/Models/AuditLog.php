<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AuditLog extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'user_id', 'entity', 'entity_id', 'action',
        'old_value', 'new_value', 'reason',
    ];

    protected function casts(): array
    {
        return ['old_value' => 'array', 'new_value' => 'array'];
    }

    protected static function booted(): void
    {
        static::updating(fn () => false);  // tidak boleh diubah
        static::deleting(fn () => false);  // tidak boleh dihapus
    }
}
