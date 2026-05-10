<?php

namespace App\Services;

use App\Enums\EntryType;
use App\Models\CashEntry;
use App\Models\MaterialDelivery;
use App\Models\Supplier;
use App\Models\SupplierPayment;
use App\Models\SupplierSettlement;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class SupplierPaymentService
{
    use OpensSupplierSettlement;

    public function recordPayment(
        Supplier $supplier,
        float    $amount,
        string   $date,
        string   $notes = ''
    ): SupplierPayment {
        if ($amount <= 0) {
            throw new InvalidArgumentException('المبلغ يجب أن يكون أكبر من صفر.');
        }

        if (now()->parse($date)->startOfDay()->gt(now()->startOfDay())) {
            throw new InvalidArgumentException('لا يمكن تحديد تاريخ مستقبلي.');
        }

        return DB::transaction(function () use ($supplier, $amount, $date, $notes) {
            $this->ensureOpenSettlement($supplier, $date);

            $cashEntry = CashEntry::create([
                'entry_type'  => EntryType::RawMaterial->value,
                'amount'      => $amount,
                'description' => 'دفعة للمورد: ' . $supplier->name,
                'notes'       => $notes ?: null,
            ]);

            return SupplierPayment::create([
                'supplier_id'   => $supplier->id,
                'cash_entry_id' => $cashEntry->id,
                'payment_date'  => $date,
                'is_settlement' => false,
                'settlement_id' => $supplier->open_settlement_id,
            ]);
        });
    }

    public function deletePayment(SupplierPayment $payment): void
    {
        if ($payment->is_settlement) {
            throw new InvalidArgumentException('لا يمكن حذف دفعة التسوية مباشرة. احذف التسوية بالكامل.');
        }

        DB::transaction(function () use ($payment) {
            $cashEntry = $payment->cashEntry;
            $payment->delete();
            $cashEntry->delete();
        });
    }

    public function recordSettlement(
        Supplier $supplier,
        float    $amountPaid,
        string   $date,
        string   $notes = ''
    ): SupplierSettlement {
        if (! $supplier->open_settlement_id) {
            throw new InvalidArgumentException('لا توجد فترة مفتوحة لهذا المورد.');
        }

        if (now()->parse($date)->startOfDay()->gt(now()->startOfDay())) {
            throw new InvalidArgumentException('لا يمكن تحديد تاريخ مستقبلي.');
        }

        if ($amountPaid < 0) {
            throw new InvalidArgumentException('مبلغ التسوية لا يمكن أن يكون سالباً.');
        }

        return DB::transaction(function () use ($supplier, $amountPaid, $date, $notes) {
            $settlement = SupplierSettlement::findOrFail($supplier->open_settlement_id);

            $totalDelivered = MaterialDelivery::where('settlement_id', $settlement->id)
                ->where('delivery_type', 'debit')
                ->sum('total_price');

            $totalPaid = SupplierPayment::where('settlement_id', $settlement->id)
                ->join('cash_entries', 'supplier_payments.cash_entry_id', '=', 'cash_entries.id')
                ->sum('cash_entries.amount');

            $cashEntry = null;
            if ($amountPaid > 0) {
                $cashEntry = CashEntry::create([
                    'entry_type'  => EntryType::RawMaterial->value,
                    'amount'      => $amountPaid,
                    'description' => 'تسوية حساب المورد: ' . $supplier->name,
                    'notes'       => $notes ?: null,
                ]);

                SupplierPayment::create([
                    'supplier_id'   => $supplier->id,
                    'cash_entry_id' => $cashEntry->id,
                    'payment_date'  => $date,
                    'is_settlement' => true,
                    'settlement_id' => $settlement->id,
                ]);
            }

            $settlement->update([
                'status'                   => 'closed',
                'period_to'                => $date,
                'settlement_date'          => $date,
                'snapshot_total_delivered' => $totalDelivered,
                'snapshot_total_paid'      => $totalPaid,
                'amount_paid'              => $amountPaid,
                'cash_entry_id'            => $cashEntry?->id,
                'notes'                    => $notes ?: null,
            ]);

            $supplier->update(['open_settlement_id' => null]);

            return $settlement;
        });
    }
}
