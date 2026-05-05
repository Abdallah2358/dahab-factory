<?php

namespace App\Filament\Resources\OrderResource\Pages;

use App\Filament\Resources\OrderResource;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use App\Models\Order;

class ViewOrder extends ViewRecord
{
    protected static string $resource = OrderResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make()->label('تعديل'),
            Actions\DeleteAction::make()
                ->label('حذف')
                ->before(function (Order $record, Actions\DeleteAction $action) {
                    if ($record->payments()->exists()) {
                        Notification::make()
                            ->danger()
                            ->title('لا يمكن الحذف')
                            ->body('هذا الطلب لديه دفعات. احذف الدفعات أولاً.')
                            ->send();
                        $action->cancel();
                    }
                }),
        ];
    }
}
