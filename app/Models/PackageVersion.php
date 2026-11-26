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
        'duration_days', 'nights', 'itinerary_days',
    ];

    protected function casts(): array
    {
        return ['effective_date' => 'date', 'duration_days' => 'integer', 'nights' => 'integer', 'itinerary_days' => 'array'];
    }

    /** Render once at trip creation; the trip never reads live template content. */
    public function itineraryForTrip(): ?string
    {
        $parts = collect($this->itinerary_days ?? [])->sortBy('day_no')
            ->map(fn ($day) => 'Hari '.$day['day_no'].' - '.$day['title']."\n".$day['description'])
            ->prepend($this->itinerary)->filter(fn ($text) => trim((string) $text) !== '');

        return $parts->isEmpty() ? null : $parts->implode("\n\n");
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
