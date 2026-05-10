<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SupplierPayment extends Model
{
    protected $fillable = [
        'supplier_id', 'cash_entry_id', 'payment_date',
        'is_settlement', 'settlement_id', 'notes',
    ];

    protected $casts = [
        'payment_date'  => 'date',
        'is_settlement' => 'boolean',
    ];

    public function supplier()
    {
        return $this->belongsTo(Supplier::class);
    }

    public function cashEntry()
    {
        return $this->belongsTo(CashEntry::class);
    }

    public function settlement()
    {
        return $this->belongsTo(SupplierSettlement::class, 'settlement_id');
    }
}
