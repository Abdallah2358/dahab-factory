<?php

namespace App\Filament\Resources;

use App\Filament\Resources\MaterialTypeResource\Pages;
use App\Models\MaterialType;
use App\Models\Unit;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class MaterialTypeResource extends Resource
{
    protected static ?string $model = MaterialType::class;

    protected static ?string $navigationIcon = 'heroicon-o-cube';
    protected static ?string $navigationLabel = 'أنواع المواد';
    protected static ?string $navigationGroup = 'المخزن';
    protected static ?int $navigationSort = 2;
    protected static ?string $modelLabel = 'نوع مادة';
    protected static ?string $pluralModelLabel = 'أنواع المواد';

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\TextInput::make('name')
                ->label('الاسم')
                ->required()
                ->maxLength(100),
            Forms\Components\Select::make('default_unit_id')
                ->label('وحدة القياس الافتراضية')
                ->options(Unit::pluck('name', 'id'))
                ->required()
                ->searchable(),
            Forms\Components\TextInput::make('default_price')
                ->label('السعر الافتراضي (ج.م)')
                ->numeric()
                ->nullable()
                ->minValue(0),
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
                    ->label('الاسم')
                    ->searchable(),
                Tables\Columns\TextColumn::make('defaultUnit.abbreviation')
                    ->label('الوحدة'),
                Tables\Columns\TextColumn::make('default_price')
                    ->label('السعر الافتراضي')
                    ->money('EGP')
                    ->sortable(),
            ])
            ->actions([
                Tables\Actions\EditAction::make()->label('تعديل'),
                Tables\Actions\DeleteAction::make()
                    ->label('حذف')
                    ->before(function ($record, $action) {
                        if ($record->deliveries()->exists()) {
                            Notification::make()
                                ->title('لا يمكن الحذف')
                                ->body('هذا النوع مستخدم في توريدات مسجلة.')
                                ->danger()
                                ->send();
                            $action->cancel();
                        }
                    }),
            ])
            ->bulkActions([]);
    }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListMaterialTypes::route('/'),
            'create' => Pages\CreateMaterialType::route('/create'),
            'edit'   => Pages\EditMaterialType::route('/{record}/edit'),
        ];
    }
}
