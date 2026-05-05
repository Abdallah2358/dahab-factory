<?php

namespace App\Filament\Resources\ClientResource\RelationManagers;

use App\Filament\Resources\OrderResource;
use App\Models\Order;
use App\Services\PaymentService;
use Carbon\Carbon;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class OrdersRelationManager extends RelationManager
{
    protected static string $relationship = 'orders';
    protected static ?string $title = 'الطلبات';

    public function canCreate(): bool
    {
        return $this->pageClass === \App\Filament\Resources\ClientResource\Pages\ViewClient::class;
    }

    public function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\TextInput::make('quantity')
                ->label('الكمية (طوبة)')
                ->required()
                ->integer()
                ->minValue(1),

            Forms\Components\TextInput::make('total_price')
                ->label('السعر الإجمالي (ج.م)')
                ->required()
                ->numeric()
                ->minValue(0.01),

            Forms\Components\DatePicker::make('order_date')
                ->label('تاريخ الطلب')
                ->required()
                ->default(today())
                ->maxDate(today()),

            Forms\Components\Textarea::make('notes')
                ->label('ملاحظات')
                ->columnSpanFull(),

            Forms\Components\TextInput::make('deposit_amount')
                ->label('دفعة أولى (اختياري)')
                ->numeric()
                ->minValue(0)
                ->default(null)
                ->helperText('اتركه فارغاً إذا لم يكن هناك دفعة أولى')
                ->visibleOn('create'),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('order_date')
                    ->label('التاريخ')
                    ->date()
                    ->sortable(),

                Tables\Columns\TextColumn::make('quantity')
                    ->label('الكمية')
                    ->getStateUsing(fn (Order $record) => number_format($record->quantity)),

                Tables\Columns\TextColumn::make('total_price')
                    ->label('السعر الكلي')
                    ->getStateUsing(fn (Order $record) => number_format($record->total_price, 2) . ' ج.م'),

                Tables\Columns\TextColumn::make('amount_paid')
                    ->label('المدفوع')
                    ->getStateUsing(fn (Order $record) => number_format($record->amount_paid, 2) . ' ج.م'),

                Tables\Columns\TextColumn::make('remaining')
                    ->label('المتبقي')
                    ->getStateUsing(fn (Order $record) => number_format($record->remaining, 2) . ' ج.م')
                    ->color(fn (Order $record) => $record->remaining > 0 ? 'danger' : 'success'),

                Tables\Columns\BadgeColumn::make('status')
                    ->label('الحالة')
                    ->getStateUsing(fn (Order $record) => $record->status)
                    ->colors(['success' => 'paid', 'warning' => 'pending'])
                    ->formatStateUsing(fn (string $state) => $state === 'paid' ? 'مدفوع' : 'معلق'),
            ])
            ->recordUrl(fn (Order $record) => OrderResource::getUrl('view', ['record' => $record]))
            ->filters([
                Filter::make('order_date')
                    ->label('التاريخ')
                    ->form([
                        Forms\Components\DatePicker::make('from')->label('من')->default(today()),
                        Forms\Components\DatePicker::make('to')->label('إلى')->default(today()),
                    ])
                    ->query(function ($query, array $data) {
                        return $query
                            ->when($data['from'] ?? null, fn ($q, $d) => $q->whereDate('order_date', '>=', $d))
                            ->when($data['to'] ?? null, fn ($q, $d) => $q->whereDate('order_date', '<=', $d));
                    })
                    ->indicateUsing(function (array $data): array {
                        $indicators = [];
                        if ($data['from'] ?? null)
                            $indicators[] = Tables\Filters\Indicator::make('من: ' . Carbon::parse($data['from'])->format('Y-m-d'))->removeField('from');
                        if ($data['to'] ?? null)
                            $indicators[] = Tables\Filters\Indicator::make('إلى: ' . Carbon::parse($data['to'])->format('Y-m-d'))->removeField('to');
                        return $indicators;
                    }),

                SelectFilter::make('status')
                    ->label('الحالة')
                    ->options(['paid' => 'مدفوع', 'pending' => 'معلق'])
                    ->query(function ($query, array $data) {
                        if (! $data['value']) return $query;
                        $ids = Order::with('payments.cashEntry')->get()
                            ->filter(fn ($o) => $o->status === $data['value'])
                            ->pluck('id');
                        return $query->whereIn('id', $ids);
                    }),
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make()
                    ->label('طلب جديد')
                    ->using(function (array $data, string $model) {
                        $depositAmount = $data['deposit_amount'] ?? null;
                        unset($data['deposit_amount']);

                        $order = $model::create($data);

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
                    }),
            ])
            ->actions([
                Tables\Actions\Action::make('pay')
                    ->label('تسديد')
                    ->icon('heroicon-o-banknotes')
                    ->color('success')
                    ->visible(fn (Order $record) => $record->remaining > 0)
                    ->form([
                        Forms\Components\TextInput::make('amount')
                            ->label('المبلغ (ج.م)')
                            ->required()
                            ->numeric()
                            ->minValue(0.01)
                            ->default(fn (Order $record) => $record->remaining),

                        Forms\Components\DatePicker::make('payment_date')
                            ->label('تاريخ الدفع')
                            ->required()
                            ->default(today())
                            ->maxDate(today()),

                        Forms\Components\TextInput::make('description')
                            ->label('وصف (اختياري)')
                            ->maxLength(255),
                    ])
                    ->action(function (Order $record, array $data) {
                        try {
                            app(PaymentService::class)->recordPayment(
                                order: $record,
                                amount: (float) $data['amount'],
                                date: $data['payment_date'],
                                description: $data['description'] ?? '',
                            );
                            Notification::make()->success()->title('تم تسجيل الدفعة بنجاح')->send();
                        } catch (\InvalidArgumentException $e) {
                            Notification::make()->danger()->title('خطأ')->body($e->getMessage())->send();
                        }
                    }),

                Tables\Actions\ViewAction::make()->label('عرض'),
                Tables\Actions\EditAction::make()->label('تعديل'),

                Tables\Actions\DeleteAction::make()
                    ->label('حذف')
                    ->before(function (Order $record, Tables\Actions\DeleteAction $action) {
                        if ($record->payments()->exists()) {
                            Notification::make()
                                ->danger()
                                ->title('لا يمكن الحذف')
                                ->body('هذا الطلب لديه دفعات. احذف الدفعات أولاً.')
                                ->send();
                            $action->cancel();
                        }
                    }),
            ])
            ->defaultSort('order_date', 'desc');
    }
}
