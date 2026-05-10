<?php

namespace App\Filament\Resources\MaterialTypeResource\Pages;

use App\Filament\Resources\MaterialTypeResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListMaterialTypes extends ListRecords
{
    protected static string $resource = MaterialTypeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
