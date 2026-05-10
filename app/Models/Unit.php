<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Unit extends Model
{
    protected $fillable = ['name', 'abbreviation'];

    public function materialTypes()
    {
        return $this->hasMany(MaterialType::class, 'default_unit_id');
    }

    public function deliveries()
    {
        return $this->hasMany(MaterialDelivery::class);
    }
}
