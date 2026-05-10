<?php

namespace App\Filament\Resources;

use App\Filament\Resources\SupplierResource\Pages;
use App\Models\Supplier;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class SupplierResource extends Resource
{
    protected static ?string $model = Supplier::class;

    protected static ?string $navigationIcon = 'heroicon-o-building-storefront';
    protected static ?string $navigationLabel = 'الموردون';
    protected static ?string $navigationGroup = 'الموردون';
    protected static ?string $modelLabel = 'مورد';
    protected static ?string $pluralModelLabel = 'الموردون';

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\TextInput::make('name')
                ->label('اسم المورد')
                ->required()
                ->maxLength(200),
            Forms\Components\TextInput::make('phone')
                ->label('رقم الهاتف')
                ->tel()
                ->nullable()
                ->maxLength(20),
            Forms\Components\Textarea::make('notes')
                ->label('ملاحظات')
                ->nullable()
                ->columnSpanFull(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('اسم المورد')
                    ->searchable(['name', 'phone'])
                    ->sortable(),
                Tables\Columns\TextColumn::make('phone')
                    ->label('الهاتف')
                    ->placeholder('—'),
                Tables\Columns\TextColumn::make('current_balance')
                    ->label('الرصيد الحالي')
                    ->getStateUsing(fn(Supplier $record) => $record->current_balance)
                    ->formatStateUsing(fn($state) => number_format($state, 2) . ' ج.م')
                    ->color(fn(Supplier $record) => $record->current_balance > 0 ? 'danger' : 'success'),
                Tables\Columns\TextColumn::make('open_settlement_status')
                    ->label('حالة الفترة')
                    ->badge()
                    ->getStateUsing(fn(Supplier $record) => $record->open_settlement_id ? 'مفتوح' : 'لا يوجد')
                    ->color(fn(string $state) => $state === 'مفتوح' ? 'warning' : 'gray'),
            ])
            ->actions([
                Tables\Actions\ViewAction::make()->label('عرض'),
                Tables\Actions\EditAction::make()->label('تعديل'),
                Tables\Actions\DeleteAction::make()
                    ->label('حذف')
                    ->before(function (Supplier $record, Tables\Actions\DeleteAction $action) {
                        if ($record->deliveries()->exists() || $record->payments()->exists()) {
                            Notification::make()
                                ->danger()
                                ->title('لا يمكن الحذف')
                                ->body('هذا المورد لديه توريدات أو مدفوعات مسجلة.')
                                ->send();
                            $action->cancel();
                        }
                    }),
            ])
            ->defaultSort('created_at', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListSuppliers::route('/'),
            'create' => Pages\CreateSupplier::route('/create'),
            'view'   => Pages\ViewSupplier::route('/{record}'),
            'edit'   => Pages\EditSupplier::route('/{record}/edit'),
        ];
    }
}
