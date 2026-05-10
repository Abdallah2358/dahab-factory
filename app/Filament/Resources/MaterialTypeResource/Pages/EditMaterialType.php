<?php

namespace App\Filament\Resources\MaterialTypeResource\Pages;

use App\Filament\Resources\MaterialTypeResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditMaterialType extends EditRecord
{
    protected static string $resource = MaterialTypeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
