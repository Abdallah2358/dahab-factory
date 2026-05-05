<?php

namespace App\Filament\Resources\OrderResource\RelationManagers;

use App\Models\Payment;
use App\Services\PaymentService;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Forms\Form;

class PaymentsRelationManager extends RelationManager
{
    protected static string $relationship = 'payments';
    protected static ?string $title = 'الدفعات';

    public function form(Form $form): Form
    {
        return $form->schema([]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('payment_date')
                    ->label('تاريخ الدفع')
                    ->date()
                    ->sortable(),

                Tables\Columns\TextColumn::make('cashEntry.amount')
                    ->label('المبلغ (ج.م)')
                    ->getStateUsing(fn (Payment $record) => number_format($record->cashEntry->amount, 2) . ' ج.م')
                    ->color('success'),

                Tables\Columns\TextColumn::make('cashEntry.description')
                    ->label('البيان'),

                Tables\Columns\IconColumn::make('is_deposit')
                    ->label('دفعة أولى')
                    ->boolean()
                    ->trueIcon('heroicon-o-check-circle')
                    ->falseIcon('heroicon-o-minus-circle')
                    ->trueColor('warning')
                    ->falseColor('gray'),
            ])
            ->actions([
                Tables\Actions\Action::make('edit')
                    ->label('تعديل')
                    ->icon('heroicon-o-pencil-square')
                    ->color('warning')
                    ->fillForm(fn (Payment $record) => [
                        'amount'       => $record->cashEntry->amount,
                        'payment_date' => $record->payment_date,
                        'description'  => $record->cashEntry->description,
                    ])
                    ->form([
                        Forms\Components\TextInput::make('amount')
                            ->label('المبلغ (ج.م)')
                            ->required()
                            ->numeric()
                            ->minValue(0.01),

                        Forms\Components\DatePicker::make('payment_date')
                            ->label('تاريخ الدفع')
                            ->required()
                            ->maxDate(today()),

                        Forms\Components\TextInput::make('description')
                            ->label('البيان')
                            ->maxLength(255),
                    ])
                    ->action(function (Payment $record, array $data) {
                        try {
                            app(PaymentService::class)->editPayment(
                                payment: $record,
                                amount: (float) $data['amount'],
                                date: $data['payment_date'],
                                description: $data['description'] ?? '',
                            );
                            Notification::make()->success()->title('تم تعديل الدفعة')->send();
                        } catch (\InvalidArgumentException $e) {
                            Notification::make()->danger()->title('خطأ')->body($e->getMessage())->send();
                        }
                    }),

                Tables\Actions\Action::make('delete')
                    ->label('حذف')
                    ->icon('heroicon-o-trash')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->modalHeading('حذف الدفعة')
                    ->modalDescription('هل أنت متأكد من حذف هذه الدفعة؟ لا يمكن التراجع.')
                    ->modalSubmitActionLabel('نعم، احذف')
                    ->action(function (Payment $record) {
                        try {
                            app(PaymentService::class)->deletePayment($record);
                            Notification::make()->success()->title('تم حذف الدفعة')->send();
                        } catch (\Throwable $e) {
                            Notification::make()->danger()->title('خطأ')->body($e->getMessage())->send();
                        }
                    }),
            ])
            ->defaultSort('payment_date')
            ->paginated(false);
    }
}
