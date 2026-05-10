<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SupplierSettlement extends Model
{
    protected $fillable = [
        'supplier_id', 'status', 'period_from', 'period_to',
        'settlement_date', 'snapshot_total_delivered', 'snapshot_total_paid',
        'amount_paid', 'cash_entry_id', 'notes',
    ];

    protected $casts = [
        'period_from'     => 'date',
        'period_to'       => 'date',
        'settlement_date' => 'date',
    ];

    public function supplier()
    {
        return $this->belongsTo(Supplier::class);
    }

    public function cashEntry()
    {
        return $this->belongsTo(CashEntry::class);
    }

    public function deliveries()
    {
        return $this->hasMany(MaterialDelivery::class, 'settlement_id');
    }

    public function payments()
    {
        return $this->hasMany(SupplierPayment::class, 'settlement_id');
    }
}
