<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MaterialDelivery extends Model
{
    protected $fillable = [
        'delivery_type', 'supplier_id', 'material_type_id', 'unit_id',
        'quantity', 'unit_price', 'total_price', 'delivery_date',
        'cash_entry_id', 'settlement_id', 'notes',
    ];

    protected $casts = [
        'delivery_date' => 'date',
        'quantity'      => 'decimal:3',
        'unit_price'    => 'decimal:2',
        'total_price'   => 'decimal:2',
    ];

    public function supplier()
    {
        return $this->belongsTo(Supplier::class);
    }

    public function materialType()
    {
        return $this->belongsTo(MaterialType::class);
    }

    public function unit()
    {
        return $this->belongsTo(Unit::class);
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
