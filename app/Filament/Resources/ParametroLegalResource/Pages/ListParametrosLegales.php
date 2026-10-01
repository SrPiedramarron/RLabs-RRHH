<?php

namespace App\Filament\Resources\ParametroLegalResource\Pages;

use App\Filament\Resources\ParametroLegalResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListParametrosLegales extends ListRecords
{
    protected static string $resource = ParametroLegalResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
