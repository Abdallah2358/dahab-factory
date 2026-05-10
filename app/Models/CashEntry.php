<?php

namespace App\Models;

use App\Enums\EntryType;
use Illuminate\Database\Eloquent\Model;

class CashEntry extends Model
{
    protected $fillable = ['entry_type', 'amount', 'description', 'notes'];

    protected $casts = [
        'entry_type' => EntryType::class,
        'amount'     => 'decimal:2',
    ];

    public function payment()
    {
        return $this->hasOne(Payment::class);
    }
}
