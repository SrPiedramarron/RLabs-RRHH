<?php

namespace App\Filament\Resources\PayrollConceptResource\Pages;

use App\Filament\Resources\PayrollConceptResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListPayrollConcepts extends ListRecords
{
    protected static string $resource = PayrollConceptResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
