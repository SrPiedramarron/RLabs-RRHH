<?php

namespace App\Filament\Resources\RecordatorioResource\Pages;

use App\Filament\Resources\RecordatorioResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditRecordatorio extends EditRecord
{
    protected static string $resource = RecordatorioResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
