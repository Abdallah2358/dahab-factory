<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Payment extends Model
{
    protected $fillable = ['order_id', 'cash_entry_id', 'payment_date', 'is_deposit'];

    protected $casts = ['payment_date' => 'date', 'is_deposit' => 'boolean'];

    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    public function cashEntry()
    {
        return $this->belongsTo(CashEntry::class);
    }
}
