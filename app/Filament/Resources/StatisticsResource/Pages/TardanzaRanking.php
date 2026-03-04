<?php
// ============================================================
// Archivo: app/Filament/Resources/StatisticsResource/Pages/TardanzaRanking.php
// ============================================================
namespace App\Filament\Resources\StatisticsResource\Pages;

use App\Filament\Resources\StatisticsResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class TardanzaRanking extends ListRecords
{
    protected static string $resource = StatisticsResource::class;

    protected function getHeaderWidgets(): array
    {
        return [
            StatisticsResource\Widgets\TardanzaResumenWidget::class,
        ];
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('ver_detalle')
                ->label('Detalle por Empleado')
                ->icon('heroicon-o-user')
                ->url(StatisticsResource::getUrl('by-employee'))
                ->color('gray'),

            Actions\Action::make('ver_sin_salida')
                ->label('Sin Marcación de Salida')
                ->icon('heroicon-o-arrow-right-on-rectangle')
                ->url(StatisticsResource::getUrl('no-checkout'))
                ->color('warning'),
        ];
    }
    public function getTableRecordKey(\Illuminate\Database\Eloquent\Model $record): string
{
    return (string) $record->employee_id;
}
}