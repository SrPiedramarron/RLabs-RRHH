<?php

namespace App\Filament\Resources\AfpTasaResource\Pages;

use App\Filament\Resources\AfpTasaResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListAfpTasas extends ListRecords
{
    protected static string $resource = AfpTasaResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
