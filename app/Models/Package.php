<?php

namespace App\Models;

use App\Support\Options;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Package extends Model
{
    use SoftDeletes;

    protected $fillable = ['name', 'trip_type', 'description', 'status'];

    public function latestVersion()
    {
        return $this->hasOne(PackageVersion::class)->latestOfMany('version_no');
    }

    public function getTripTypeLabelAttribute(): string
    {
        return Options::TRIP_TYPES[$this->trip_type] ?? $this->trip_type;
    }

    public function getTemplateSummaryAttribute(): string
    {
        $version = $this->latestVersion;
        if (! $version) {
            return 'Belum ada rencana layanan';
        }
        $duration = $version->duration_days
            ? $version->duration_days.' hari / '.($version->nights ?? max($version->duration_days - 1, 0)).' malam'
            : 'Durasi belum ditentukan';

        return 'v'.$version->version_no.' - '.(Options::VERSION_STATUS[$version->status] ?? $version->status).' - '.$duration;
    }

    public function getTemplateDestinationsAttribute(): string
    {
        $labels = $this->latestVersion?->items->where('item_type', 'destination')->unique('item_id')->pluck('item_label');

        return $labels && $labels->isNotEmpty() ? $labels->implode(', ') : '-';
    }

    public function versions()
    {
        return $this->hasMany(PackageVersion::class);
    }
}
