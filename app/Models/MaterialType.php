<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MaterialType extends Model
{
    protected $fillable = ['name', 'default_unit_id', 'default_price', 'notes'];

    protected $casts = ['default_price' => 'decimal:2'];

    public function defaultUnit()
    {
        return $this->belongsTo(Unit::class, 'default_unit_id');
    }

    public function deliveries()
    {
        return $this->hasMany(MaterialDelivery::class);
    }
}
