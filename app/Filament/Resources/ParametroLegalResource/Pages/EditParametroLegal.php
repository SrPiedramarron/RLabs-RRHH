<?php

namespace App\Filament\Resources\ParametroLegalResource\Pages;

use App\Filament\Resources\ParametroLegalResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditParametroLegal extends EditRecord
{
    protected static string $resource = ParametroLegalResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
