<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Order extends Model
{
    protected $fillable = ['client_id', 'quantity', 'total_price', 'order_date', 'notes'];

    protected $casts = ['order_date' => 'date', 'total_price' => 'decimal:2'];

    public function client()
    {
        return $this->belongsTo(Client::class);
    }

    public function payments()
    {
        return $this->hasMany(Payment::class)->orderBy('payment_date')->orderBy('created_at');
    }

    public function getAmountPaidAttribute(): float
    {
        return $this->payments->sum(fn ($p) => $p->cashEntry->amount ?? 0);
    }

    public function getRemainingAttribute(): float
    {
        return max(0, $this->total_price - $this->amount_paid);
    }

    public function getStatusAttribute(): string
    {
        return $this->remaining <= 0 ? 'paid' : 'pending';
    }
}
