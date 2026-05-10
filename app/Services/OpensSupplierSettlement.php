<?php

namespace App\Services;

use App\Models\Supplier;
use App\Models\SupplierSettlement;

trait OpensSupplierSettlement
{
    protected function ensureOpenSettlement(Supplier $supplier, string $date): void
    {
        if ($supplier->open_settlement_id) return;

        $settlement = SupplierSettlement::create([
            'supplier_id' => $supplier->id,
            'status'      => 'open',
            'period_from' => $date,
        ]);

        $supplier->update(['open_settlement_id' => $settlement->id]);
        $supplier->refresh();
    }
}
