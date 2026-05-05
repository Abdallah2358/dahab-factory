<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CashEntry extends Model
{
    protected $fillable = ['entry_type_id', 'amount', 'description', 'notes', 'entry_date'];

    protected $casts = ['entry_date' => 'date', 'amount' => 'decimal:2'];

    public function entryType()
    {
        return $this->belongsTo(EntryType::class);
    }

    public function payment()
    {
        return $this->hasOne(Payment::class);
    }
}
