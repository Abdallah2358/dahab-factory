<?php

namespace App\Services;

use App\Enums\EntryType;
use App\Models\CashEntry;
use App\Models\MaterialDelivery;
use App\Models\Supplier;
use App\Models\SupplierPayment;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class MaterialDeliveryService
{
    use OpensSupplierSettlement;

    public function record(array $data, ?float $depositAmount = null): MaterialDelivery
    {
        if ($data['delivery_type'] === 'debit' && empty($data['supplier_id'])) {
            throw new InvalidArgumentException('المورد مطلوب عند تسجيل توريد آجل.');
        }

        if (now()->parse($data['delivery_date'])->startOfDay()->gt(now()->startOfDay())) {
            throw new InvalidArgumentException('لا يمكن تحديد تاريخ مستقبلي.');
        }

        return DB::transaction(function () use ($data, $depositAmount) {

            if ($data['delivery_type'] === 'cash') {
                $cashEntry = CashEntry::create([
                    'entry_type'  => EntryType::RawMaterial->value,
                    'amount'      => $data['total_price'],
                    'description' => 'شراء نقدي: ' . ($data['notes'] ?? ''),
                    'notes'       => $data['notes'] ?? null,
                ]);

                return MaterialDelivery::create(array_merge($data, [
                    'cash_entry_id' => $cashEntry->id,
                    'settlement_id' => null,
                ]));
            }

            $supplier = Supplier::findOrFail($data['supplier_id']);
            $this->ensureOpenSettlement($supplier, $data['delivery_date']);

            $delivery = MaterialDelivery::create(array_merge($data, [
                'settlement_id' => $supplier->open_settlement_id,
                'cash_entry_id' => null,
            ]));

            if ($depositAmount && $depositAmount > 0) {
                if ($depositAmount > $data['total_price']) {
                    throw new InvalidArgumentException('الدفعة الأولى لا يمكن أن تتجاوز قيمة التوريد.');
                }

                $cashEntry = CashEntry::create([
                    'entry_type'  => EntryType::RawMaterial->value,
                    'amount'      => $depositAmount,
                    'description' => 'دفعة أولى عند التوريد: ' . $supplier->name,
                    'notes'       => null,
                ]);

                SupplierPayment::create([
                    'supplier_id'   => $supplier->id,
                    'cash_entry_id' => $cashEntry->id,
                    'payment_date'  => $data['delivery_date'],
                    'is_settlement' => false,
                    'settlement_id' => $supplier->open_settlement_id,
                ]);
            }

            return $delivery;
        });
    }

    public function delete(MaterialDelivery $delivery): void
    {
        DB::transaction(function () use ($delivery) {
            if ($delivery->delivery_type === 'cash' && $delivery->cashEntry) {
                $cashEntry = $delivery->cashEntry;
                $delivery->delete();
                $cashEntry->delete();
            } else {
                $delivery->delete();
            }
        });
    }
}
