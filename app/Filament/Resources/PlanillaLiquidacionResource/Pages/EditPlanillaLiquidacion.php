<?php

namespace App\Filament\Resources\PlanillaLiquidacionResource\Pages;

use App\Filament\Resources\PlanillaLiquidacionResource;
use App\Services\PlanillaService;
use Filament\Resources\Pages\EditRecord;

class EditPlanillaLiquidacion extends EditRecord
{
    protected static string $resource = PlanillaLiquidacionResource::class;

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }

    // Tras guardar el bono, recalcular automáticamente
    protected function afterSave(): void
    {
        $record = $this->getRecord();

        app(PlanillaService::class)->calcularEmpleado(
            $record->employee,
            $record->company_id,
            $record->periodo,
            ...explode('-', $record->periodo),
            bonoEspecial: floatval($record->bonos_especiales),
        );
    }
}
