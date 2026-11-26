<?php

namespace App\Models;

use App\Support\Options;
use Illuminate\Database\Eloquent\Model;

class PackageVersionItem extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'package_version_id', 'item_type', 'item_id', 'quantity', 'day_no', 'duration_qty', 'notes',
    ];

    protected function casts(): array
    {
        return ['quantity' => 'decimal:4', 'duration_qty' => 'integer', 'day_no' => 'integer'];
    }

    public function version()
    {
        return $this->belongsTo(PackageVersion::class, 'package_version_id');
    }

    public function item()
    {
        return $this->morphTo('item', 'item_type', 'item_id')->withTrashed();
    }

    public static function labelFor(string $type, $m): string
    {
        return match ($type) {
            'hotel' => $m->name.' - '.$m->room_type,
            'vehicle' => $m->type.' (kapasitas '.$m->capacity.')',
            'activity', 'destination' => $m->name,
            'meal' => Options::MEAL_TYPES[$m->meal_type] ?? $m->meal_type,
            'cost_item' => $m->category.' - '.$m->name,
            default => '-',
        };
    }

    public function getItemLabelAttribute(): string
    {
        $m = $this->item;
        if (! $m) {
            return '(data tidak ditemukan)';
        }
        $label = self::labelFor($this->item_type, $m);

        return method_exists($m, 'trashed') && $m->trashed() ? $label.' (dihapus)' : $label;
    }
}
