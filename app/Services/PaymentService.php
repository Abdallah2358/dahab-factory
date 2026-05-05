<?php

namespace App\Services;

use App\Models\CashEntry;
use App\Models\EntryType;
use App\Models\Order;
use App\Models\Payment;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class PaymentService
{
    public function recordPayment(
        Order  $order,
        float  $amount,
        string $date,
        string $description = '',
        bool   $isDeposit = false
    ): Payment {
        $order->load('payments.cashEntry');

        $remaining = $order->remaining;

        if ($amount <= 0) {
            throw new InvalidArgumentException('المبلغ يجب أن يكون أكبر من صفر.');
        }

        if ($amount > $remaining) {
            throw new InvalidArgumentException(
                "المبلغ المدخل ({$amount}) يتجاوز المبلغ المتبقي ({$remaining})."
            );
        }

        if (now()->parse($date)->startOfDay()->gt(now()->startOfDay())) {
            throw new InvalidArgumentException('لا يمكن تحديد تاريخ مستقبلي.');
        }

        $entryType = EntryType::where('code', 'client_payment')->firstOrFail();

        return DB::transaction(function () use ($order, $amount, $date, $description, $isDeposit, $entryType) {
            $cashEntry = CashEntry::create([
                'entry_type_id' => $entryType->id,
                'amount'        => $amount,
                'description'   => $description ?: "تحصيل من عميل: {$order->client->name}",
                'entry_date'    => $date,
            ]);

            return Payment::create([
                'order_id'      => $order->id,
                'cash_entry_id' => $cashEntry->id,
                'payment_date'  => $date,
                'is_deposit'    => $isDeposit,
            ]);
        });
    }

    public function editPayment(
        Payment $payment,
        float   $amount,
        string  $date,
        string  $description = ''
    ): void {
        if ($amount <= 0) {
            throw new InvalidArgumentException('المبلغ يجب أن يكون أكبر من صفر.');
        }

        if (now()->parse($date)->startOfDay()->gt(now()->startOfDay())) {
            throw new InvalidArgumentException('لا يمكن تحديد تاريخ مستقبلي.');
        }

        $payment->load('order.payments.cashEntry');
        $order = $payment->order;

        // Max allowed = remaining balance + what this payment currently holds
        $maxAllowed = $order->remaining + (float) $payment->cashEntry->amount;

        if ($amount > $maxAllowed) {
            throw new InvalidArgumentException(
                "المبلغ المدخل ({$amount}) يتجاوز الحد المسموح ({$maxAllowed})."
            );
        }

        DB::transaction(function () use ($payment, $amount, $date, $description) {
            $payment->cashEntry->update([
                'amount'      => $amount,
                'description' => $description ?: $payment->cashEntry->description,
                'entry_date'  => $date,
            ]);

            $payment->update(['payment_date' => $date]);
        });
    }

    public function deletePayment(Payment $payment): void
    {
        DB::transaction(function () use ($payment) {
            $cashEntry = $payment->cashEntry;
            $payment->delete();
            $cashEntry->delete();
        });
    }
}
