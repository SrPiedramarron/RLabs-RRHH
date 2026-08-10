<?php

namespace App\Filament\Resources\ComisionUploadResource\Pages;

use App\Filament\Resources\ComisionUploadResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListComisionUploads extends ListRecords
{
    protected static string $resource = ComisionUploadResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()
                ->label('Subir archivos del mes'),
        ];
    }
}
