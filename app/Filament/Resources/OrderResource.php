<?php

namespace App\Filament\Resources;

use App\Filament\Resources\OrderResource\Pages;
use App\Filament\Resources\OrderResource\RelationManagers\PaymentsRelationManager;
use App\Models\Order;
use App\Services\PaymentService;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Carbon\Carbon;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class OrderResource extends Resource
{
    protected static ?string $model = Order::class;
    protected static ?string $navigationIcon = 'heroicon-o-shopping-cart';
    protected static ?string $navigationLabel = 'الطلبات';
    protected static ?string $navigationGroup = 'العملاء';
    protected static ?string $modelLabel = 'طلب';
    protected static ?string $pluralModelLabel = 'الطلبات';

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Select::make('client_id')
                ->label('العميل')
                ->relationship('client', 'name')
                ->searchable()
                ->preload()
                ->required(),

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

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('client.name')
                    ->label('العميل')
                    ->searchable()
                    ->sortable(),

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
            ->filters([
                Filter::make('order_date')
                    ->label('التاريخ')
                    ->form([
                        Forms\Components\DatePicker::make('from')
                            ->label('من')
                            ->default(today()),
                        Forms\Components\DatePicker::make('to')
                            ->label('إلى')
                            ->default(today()),
                    ])
                    ->query(function ($query, array $data) {
                        return $query
                            ->when($data['from'] ?? null, fn ($q, $d) => $q->whereDate('order_date', '>=', $d))
                            ->when($data['to'] ?? null, fn ($q, $d) => $q->whereDate('order_date', '<=', $d));
                    })
                    ->indicateUsing(function (array $data): array {
                        $indicators = [];
                        if ($data['from'] ?? null) {
                            $indicators[] = Tables\Filters\Indicator::make('من: ' . Carbon::parse($data['from'])->format('Y-m-d'))
                                ->removeField('from');
                        }
                        if ($data['to'] ?? null) {
                            $indicators[] = Tables\Filters\Indicator::make('إلى: ' . Carbon::parse($data['to'])->format('Y-m-d'))
                                ->removeField('to');
                        }
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

                SelectFilter::make('client_id')
                    ->label('العميل')
                    ->relationship('client', 'name')
                    ->searchable()
                    ->preload(),
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
            ->recordUrl(fn (Order $record) => static::getUrl('view', ['record' => $record]))
            ->defaultSort('order_date', 'desc');
    }

    public static function getRelations(): array
    {
        return [
            PaymentsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListOrders::route('/'),
            'create' => Pages\CreateOrder::route('/create'),
            'view'   => Pages\ViewOrder::route('/{record}'),
            'edit'   => Pages\EditOrder::route('/{record}/edit'),
        ];
    }
}
