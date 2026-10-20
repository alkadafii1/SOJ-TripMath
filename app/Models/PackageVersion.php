<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class PackageVersion extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'package_id', 'version_no', 'effective_date', 'status',
        'itinerary', 'inclusions', 'exclusions', 'change_log',
    ];

    protected function casts(): array
    {
        return ['effective_date' => 'date'];
    }

    public function package()
    {
        return $this->belongsTo(Package::class);
    }

    public function items()
    {
        return $this->hasMany(PackageVersionItem::class);
    }
}