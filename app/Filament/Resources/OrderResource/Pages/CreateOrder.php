<?php

namespace App\Filament\Resources\OrderResource\Pages;

use App\Filament\Resources\OrderResource;
use App\Services\PaymentService;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreateOrder extends CreateRecord
{
    protected static string $resource = OrderResource::class;

    protected function handleRecordCreation(array $data): Model
    {
        $depositAmount = $data['deposit_amount'] ?? null;
        unset($data['deposit_amount']);

        $order = parent::handleRecordCreation($data);

        if ($depositAmount && $depositAmount > 0) {
            app(PaymentService::class)->recordPayment(
                order: $order,
                amount: (float) $depositAmount,
                date: $data['order_date'],
                description: 'دفعة أولى',
                isDeposit: true,
            );
        }

        return $order;
    }
}
