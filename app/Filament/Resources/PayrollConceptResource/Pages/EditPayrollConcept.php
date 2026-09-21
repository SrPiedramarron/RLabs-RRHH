<?php

namespace App\Filament\Resources\PayrollConceptResource\Pages;

use App\Filament\Resources\PayrollConceptResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditPayrollConcept extends EditRecord
{
    protected static string $resource = PayrollConceptResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
