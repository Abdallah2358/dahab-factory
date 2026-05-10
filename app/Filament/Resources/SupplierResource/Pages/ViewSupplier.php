<?php

namespace App\Filament\Resources\SupplierResource\Pages;

use App\Filament\Resources\SupplierResource;
use App\Models\MaterialDelivery;
use App\Models\MaterialType;
use App\Models\Supplier;
use App\Models\SupplierPayment;
use App\Models\Unit;
use App\Services\MaterialDeliveryService;
use App\Services\SupplierPaymentService;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Forms;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\Section;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Infolist;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use InvalidArgumentException;

class ViewSupplier extends ViewRecord
{
    protected static string $resource = SupplierResource::class;

    public function infolist(Infolist $infolist): Infolist
    {
        return $infolist->schema([
            Section::make()->schema([
                TextEntry::make('name')
                    ->label('اسم المورد'),
                TextEntry::make('phone')
                    ->label('الهاتف')
                    ->placeholder('—'),
                TextEntry::make('current_balance_display')
                    ->label('رصيد الفترة الحالية')
                    ->getStateUsing(fn(Supplier $record) => number_format($record->current_balance, 2) . ' ج.م')
                    ->color(fn(Supplier $record) => $record->current_balance > 0 ? 'danger' : 'success'),
                TextEntry::make('open_period_delivered')
                    ->label('إجمالي التوريدات الآجلة')
                    ->getStateUsing(function (Supplier $record) {
                        if (! $record->open_settlement_id) return '0.00 ج.م';
                        $total = MaterialDelivery::where('settlement_id', $record->open_settlement_id)
                            ->where('delivery_type', 'debit')
                            ->sum('total_price');
                        return number_format($total, 2) . ' ج.م';
                    }),
                TextEntry::make('open_period_paid')
                    ->label('إجمالي المدفوع في الفترة')
                    ->getStateUsing(function (Supplier $record) {
                        if (! $record->open_settlement_id) return '0.00 ج.م';
                        $total = SupplierPayment::where('settlement_id', $record->open_settlement_id)
                            ->join('cash_entries', 'supplier_payments.cash_entry_id', '=', 'cash_entries.id')
                            ->sum('cash_entries.amount');
                        return number_format($total, 2) . ' ج.م';
                    }),
            ])->columns(5),

            Section::make('تاريخ التسويات')->schema([
                RepeatableEntry::make('closed_settlements')
                    ->label('')
                    ->schema([
                        TextEntry::make('period_from')->label('من'),
                        TextEntry::make('period_to')->label('إلى'),
                        TextEntry::make('snapshot_total_delivered')
                            ->label('إجمالي التوريدات')
                            ->formatStateUsing(fn($state) => number_format($state, 2) . ' ج.م'),
                        TextEntry::make('snapshot_total_paid')
                            ->label('إجمالي المدفوع')
                            ->formatStateUsing(fn($state) => number_format($state, 2) . ' ج.م'),
                        TextEntry::make('amount_paid')
                            ->label('مبلغ التسوية')
                            ->formatStateUsing(fn($state) => number_format($state, 2) . ' ج.م'),
                        TextEntry::make('notes')->label('ملاحظات')->placeholder('—'),
                    ])->columns(6),
            ]),

            Section::make('جميع المعاملات')->schema([
                RepeatableEntry::make('all_transactions')
                    ->label('')
                    ->schema([
                        TextEntry::make('date')->label('التاريخ'),
                        TextEntry::make('type')
                            ->label('النوع')
                            ->badge()
                            ->color(fn(?string $state) => match($state) {
                                'توريد آجل'  => 'danger',
                                'توريد نقدي' => 'warning',
                                'تسوية'      => 'success',
                                default      => 'info',
                            }),
                        TextEntry::make('label')->label('البيان'),
                        TextEntry::make('amount')
                            ->label('المبلغ')
                            ->formatStateUsing(fn($state) => number_format($state, 2) . ' ج.م'),
                        TextEntry::make('period')->label('الفترة'),
                    ])->columns(5),
            ]),
        ]);
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('record_delivery')
                ->label('تسليم مواد')
                ->icon('heroicon-o-truck')
                ->color('warning')
                ->slideOver()
                ->form([
                    Forms\Components\Radio::make('delivery_type')
                        ->label('نوع التسليم')
                        ->options(['debit' => 'آجل', 'cash' => 'نقدي'])
                        ->default('debit')
                        ->inline()
                        ->live(),
                    Forms\Components\Select::make('material_type_id')
                        ->label('نوع المادة')
                        ->options(fn() => MaterialType::pluck('name', 'id'))
                        ->required()
                        ->searchable()
                        ->live()
                        ->afterStateUpdated(function (?int $state, Set $set) {
                            if ($state) {
                                $material = MaterialType::find($state);
                                $set('unit_id', $material?->default_unit_id);
                                $set('unit_price', $material?->default_price);
                            }
                        }),
                    Forms\Components\Select::make('unit_id')
                        ->label('وحدة القياس')
                        ->options(fn() => Unit::pluck('name', 'id'))
                        ->required()
                        ->searchable(),
                    Forms\Components\Grid::make(3)->schema([
                        Forms\Components\TextInput::make('quantity')
                            ->label('الكمية')
                            ->numeric()
                            ->required()
                            ->minValue(0.001)
                            ->live(debounce: 500)
                            ->afterStateUpdated(fn($state, Get $get, Set $set) =>
                                $set('total_price', round(floatval($state) * floatval($get('unit_price')), 2))
                            ),
                        Forms\Components\TextInput::make('unit_price')
                            ->label('سعر الوحدة (ج.م)')
                            ->numeric()
                            ->required()
                            ->minValue(0)
                            ->live(debounce: 500)
                            ->afterStateUpdated(fn($state, Get $get, Set $set) =>
                                $set('total_price', round(floatval($get('quantity')) * floatval($state), 2))
                            ),
                        Forms\Components\TextInput::make('total_price')
                            ->label('الإجمالي (ج.م)')
                            ->numeric()
                            ->required()
                            ->minValue(0.01),
                    ]),
                    Forms\Components\DatePicker::make('delivery_date')
                        ->label('تاريخ التسليم')
                        ->default(now())
                        ->maxDate(now())
                        ->required(),
                    Forms\Components\TextInput::make('deposit_amount')
                        ->label('دفعة أولى (اختياري)')
                        ->numeric()
                        ->nullable()
                        ->minValue(0.01)
                        ->visible(fn(Get $get) => $get('delivery_type') === 'debit'),
                    Forms\Components\Textarea::make('notes')
                        ->label('ملاحظات')
                        ->nullable(),
                ])
                ->action(function (array $data, Action $action) {
                    try {
                        $data['supplier_id'] = $this->record->id;
                        $deposit = isset($data['deposit_amount']) && $data['deposit_amount'] > 0
                            ? floatval($data['deposit_amount'])
                            : null;
                        app(MaterialDeliveryService::class)->record($data, $deposit);
                        $this->record->refresh();
                        Notification::make()->title('تم تسجيل التسليم بنجاح')->success()->send();
                    } catch (InvalidArgumentException $e) {
                        Notification::make()->title('خطأ')->body($e->getMessage())->danger()->send();
                        $action->halt();
                    }
                }),

            Action::make('record_payment')
                ->label('دفعة')
                ->icon('heroicon-o-banknotes')
                ->color('success')
                ->form([
                    Forms\Components\TextInput::make('amount')
                        ->label('المبلغ (ج.م)')
                        ->numeric()
                        ->required()
                        ->minValue(0.01),
                    Forms\Components\DatePicker::make('payment_date')
                        ->label('تاريخ الدفعة')
                        ->default(now())
                        ->maxDate(now())
                        ->required(),
                    Forms\Components\Textarea::make('notes')
                        ->label('ملاحظات')
                        ->nullable(),
                ])
                ->action(function (array $data, Action $action) {
                    try {
                        app(SupplierPaymentService::class)->recordPayment(
                            $this->record,
                            floatval($data['amount']),
                            $data['payment_date'],
                            $data['notes'] ?? ''
                        );
                        $this->record->refresh();
                        Notification::make()->title('تم تسجيل الدفعة بنجاح')->success()->send();
                    } catch (InvalidArgumentException $e) {
                        Notification::make()->title('خطأ')->body($e->getMessage())->danger()->send();
                        $action->halt();
                    }
                }),

            Action::make('record_settlement')
                ->label('تسوية')
                ->icon('heroicon-o-check-circle')
                ->color('primary')
                ->requiresConfirmation()
                ->modalHeading('تسوية حساب المورد')
                ->modalDescription('سيتم إغلاق الفترة الحالية. لا يمكن التراجع عن هذا الإجراء.')
                ->form([
                    Forms\Components\Placeholder::make('current_balance_display')
                        ->label('الرصيد الحالي')
                        ->content(fn() => number_format($this->record->current_balance, 2) . ' ج.م'),
                    Forms\Components\TextInput::make('amount_paid')
                        ->label('المبلغ المدفوع عند التسوية (ج.م)')
                        ->numeric()
                        ->default(fn() => $this->record->current_balance)
                        ->minValue(0)
                        ->helperText('يمكن تركه صفراً لإغلاق الفترة بدون دفع'),
                    Forms\Components\DatePicker::make('settlement_date')
                        ->label('تاريخ التسوية')
                        ->default(now())
                        ->maxDate(now())
                        ->required(),
                    Forms\Components\Textarea::make('notes')
                        ->label('ملاحظات')
                        ->nullable(),
                ])
                ->action(function (array $data, Action $action) {
                    try {
                        app(SupplierPaymentService::class)->recordSettlement(
                            $this->record,
                            floatval($data['amount_paid'] ?? 0),
                            $data['settlement_date'],
                            $data['notes'] ?? ''
                        );
                        $this->record->refresh();
                        Notification::make()->title('تم إغلاق الفترة بنجاح')->success()->send();
                    } catch (InvalidArgumentException $e) {
                        Notification::make()->title('خطأ')->body($e->getMessage())->danger()->send();
                        $action->halt();
                    }
                }),

            EditAction::make()->label('تعديل'),
        ];
    }
}
