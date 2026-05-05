<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EntryType extends Model
{
    protected $fillable = ['name', 'code', 'direction', 'description'];

    public function cashEntries()
    {
        return $this->hasMany(CashEntry::class);
    }
}
