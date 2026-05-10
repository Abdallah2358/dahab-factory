<?php

namespace App\Filament\Pages;

use App\Models\MaterialType;
use App\Models\Supplier;
use App\Models\Unit;
use App\Services\MaterialDeliveryService;
use Filament\Actions\Action;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use InvalidArgumentException;

class RawMaterialsPage extends Page
{
    protected static ?string $title = 'توريد الخامات';
    protected static ?string $navigationIcon = 'heroicon-o-truck';
    protected static ?string $navigationLabel = 'توريد خامات';
    protected static ?string $navigationGroup = 'المخزن';
    protected static ?int $navigationSort = 1;
    protected static string $view = 'filament.pages.raw-materials-page';
    protected static ?string $slug = 'raw-materials';

    public int $selectedMonth;
    public int $selectedYear;

    public function mount(): void
    {
        $this->selectedMonth = now()->month;
        $this->selectedYear  = now()->year;
    }

    public function getMaterials(): \Illuminate\Database\Eloquent\Collection
    {
        return MaterialType::with('defaultUnit')
            ->withSum([
                'deliveries as month_quantity' => fn ($q) => $q
                    ->whereMonth('delivery_date', $this->selectedMonth)
                    ->whereYear('delivery_date', $this->selectedYear),
            ], 'quantity')
            ->orderBy('name')
            ->get();
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('recordDelivery')
                ->label('استلام مادة')
                ->icon('heroicon-o-arrow-down-tray')
                ->color('warning')
                ->slideOver()
                ->mountUsing(function (?Form $form, array $arguments): void {
                    if (empty($arguments['material_type_id'])) {
                        $form?->fill(['delivery_type' => 'debit']);
                        return;
                    }
                    $material = MaterialType::find($arguments['material_type_id']);
                    $form?->fill([
                        'material_type_id' => $arguments['material_type_id'],
                        'delivery_type'    => 'debit',
                        'unit_id'          => $material?->default_unit_id,
                        'unit_price'       => $material?->default_price,
                    ]);
                })
                ->form($this->deliveryFormSchema())
                ->action(function (array $data) {
                    try {
                        app(MaterialDeliveryService::class)->record($data);
                        Notification::make()->title('تم تسجيل الاستلام بنجاح')->success()->send();
                    } catch (InvalidArgumentException $e) {
                        Notification::make()->title('خطأ')->body($e->getMessage())->danger()->send();
                    }
                }),
        ];
    }

    private function deliveryFormSchema(): array
    {
        return [
            Forms\Components\Select::make('material_type_id')
                ->label('نوع المادة')
                ->options(fn () => MaterialType::pluck('name', 'id'))
                ->required()
                ->searchable()
                ->live()
                ->afterStateUpdated(function (?int $state, Set $set) {
                    if (! $state) return;
                    $material = MaterialType::find($state);
                    if (! $material) return;
                    $set('unit_id', $material->default_unit_id);
                    if ($material->default_price !== null) {
                        $set('unit_price', $material->default_price);
                    }
                }),
            Forms\Components\Radio::make('delivery_type')
                ->label('نوع التوريد')
                ->options(['debit' => 'آجل', 'cash' => 'نقدي'])
                ->default('debit')
                ->inline()
                ->live(),
            Forms\Components\Select::make('supplier_id')
                ->label('المورد')
                ->options(fn () => Supplier::pluck('name', 'id'))
                ->searchable()
                ->nullable()
                ->required(fn (Get $get) => $get('delivery_type') === 'debit'),
            Forms\Components\TextInput::make('quantity')
                ->label('الكمية')
                ->numeric()
                ->required()
                ->minValue(0.001)
                ->live(debounce: 500)
                ->afterStateUpdated(fn ($state, Get $get, Set $set) =>
                    $set('total_price', $state && $get('unit_price')
                        ? round(floatval($state) * floatval($get('unit_price')), 2)
                        : $get('total_price'))
                ),
            Forms\Components\Select::make('unit_id')
                ->label('وحدة القياس')
                ->options(fn () => Unit::pluck('name', 'id'))
                ->required()
                ->searchable(),
            Forms\Components\TextInput::make('unit_price')
                ->label('سعر الوحدة (ج.م)')
                ->numeric()
                ->required()
                ->minValue(0)
                ->live(debounce: 500)
                ->afterStateUpdated(fn ($state, Get $get, Set $set) =>
                    $set('total_price', $state && $get('quantity')
                        ? round(floatval($get('quantity')) * floatval($state), 2)
                        : $get('total_price'))
                ),
            Forms\Components\TextInput::make('total_price')
                ->label('الإجمالي (ج.م)')
                ->numeric()
                ->required()
                ->minValue(0.01),
            Forms\Components\DatePicker::make('delivery_date')
                ->label('تاريخ التوريد')
                ->default(now())
                ->maxDate(now())
                ->required(),
            Forms\Components\Textarea::make('notes')
                ->label('ملاحظات')
                ->nullable(),
        ];
    }
}
