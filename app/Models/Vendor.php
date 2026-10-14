<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Vendor extends Model
{
    use SoftDeletes;

    protected $fillable = ['name', 'category', 'contact', 'notes', 'status'];

    public function hotels() { return $this->hasMany(Hotel::class); }
    public function vehicles() { return $this->hasMany(Vehicle::class); }
    public function activities() { return $this->hasMany(Activity::class); }
    public function meals() { return $this->hasMany(Meal::class); }
    public function costItems() { return $this->hasMany(CostItem::class); }
}