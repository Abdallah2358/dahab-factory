<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Supplier extends Model
{
    protected $fillable = ['name', 'phone', 'notes', 'open_settlement_id'];

    public function settlements()
    {
        return $this->hasMany(SupplierSettlement::class)->orderBy('period_from', 'desc');
    }

    public function openSettlement()
    {
        return $this->belongsTo(SupplierSettlement::class, 'open_settlement_id');
    }

    public function deliveries()
    {
        return $this->hasMany(MaterialDelivery::class);
    }

    public function payments()
    {
        return $this->hasMany(SupplierPayment::class);
    }

    public function getCurrentBalanceAttribute(): float
    {
        if (! $this->open_settlement_id) return 0;

        $delivered = MaterialDelivery::where('settlement_id', $this->open_settlement_id)
            ->where('delivery_type', 'debit')
            ->sum('total_price');

        $paid = SupplierPayment::where('settlement_id', $this->open_settlement_id)
            ->join('cash_entries', 'supplier_payments.cash_entry_id', '=', 'cash_entries.id')
            ->sum('cash_entries.amount');

        return max(0, $delivered - $paid);
    }

    public function getClosedSettlementsAttribute(): array
    {
        return $this->settlements()
            ->where('status', 'closed')
            ->orderByDesc('settlement_date')
            ->get()
            ->map(fn($s) => [
                'period_from'              => $s->period_from?->format('Y-m-d'),
                'period_to'                => $s->period_to?->format('Y-m-d'),
                'snapshot_total_delivered' => $s->snapshot_total_delivered,
                'snapshot_total_paid'      => $s->snapshot_total_paid,
                'amount_paid'              => $s->amount_paid,
                'notes'                    => $s->notes,
            ])
            ->toArray();
    }

    public function getAllTransactionsAttribute(): array
    {
        $deliveries = $this->deliveries()
            ->with(['materialType', 'unit', 'settlement'])
            ->get()
            ->map(fn($d) => [
                'date'   => $d->delivery_date->format('Y-m-d'),
                'type'   => $d->delivery_type === 'cash' ? 'توريد نقدي' : 'توريد آجل',
                'label'  => $d->materialType->name . ' (' . $d->quantity . ' ' . $d->unit->abbreviation . ')',
                'amount' => $d->total_price,
                'period' => $d->settlement?->status === 'closed'
                    ? $d->settlement->period_from->format('Y-m-d')
                    : 'مفتوح',
            ]);

        $payments = $this->payments()
            ->with(['cashEntry', 'settlement'])
            ->get()
            ->map(fn($p) => [
                'date'   => $p->payment_date->format('Y-m-d'),
                'type'   => $p->is_settlement ? 'تسوية' : 'دفعة',
                'label'  => $p->cashEntry->description,
                'amount' => $p->cashEntry->amount,
                'period' => $p->settlement?->status === 'closed'
                    ? $p->settlement->period_from->format('Y-m-d')
                    : 'مفتوح',
            ]);

        return $deliveries->merge($payments)->sortBy('date')->values()->toArray();
    }
}
