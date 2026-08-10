<?php

namespace App\Filament\Resources\PlanillaLiquidacionResource\Pages;

use App\Filament\Resources\PlanillaLiquidacionResource;
use App\Models\PlanillaLiquidacion;
use Filament\Actions\Action;
use Filament\Resources\Pages\Page;
use Filament\Resources\Pages\Concerns\InteractsWithRecord;

class DetallePlanillaLiquidacion extends Page
{
    use InteractsWithRecord;

    protected static string $resource = PlanillaLiquidacionResource::class;
    protected static string $view     = 'filament.resources.planilla-liquidacion.detalle';

    public function mount(int|string $record): void
    {
        $this->record = $this->resolveRecord($record);
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('volver')
                ->label('Volver')
                ->icon('heroicon-o-arrow-left')
                ->url(PlanillaLiquidacionResource::getUrl('index'))
                ->color('gray'),
        ];
    }

    public function getTitle(): string
    {
        return 'Liquidacion — ' . $this->record->apellidos . ', ' . $this->record->nombres
             . ' — ' . $this->record->mes_nombre;
    }
}
