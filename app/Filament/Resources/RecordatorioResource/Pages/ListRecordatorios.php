<?php

namespace App\Filament\Resources\RecordatorioResource\Pages;

use App\Filament\Resources\RecordatorioResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListRecordatorios extends ListRecords
{
    protected static string $resource = RecordatorioResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
