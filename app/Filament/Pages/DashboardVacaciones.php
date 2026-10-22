<?php

namespace App\Filament\Pages;

use App\Helpers\CompanyContext;
use App\Services\VacacionesPorPeriodoService;
use Carbon\Carbon;
use Filament\Actions\Action;
use Filament\Pages\Page;

class DashboardVacaciones extends Page
{
    use \BezhanSalleh\FilamentShield\Traits\HasPageShield;

    protected static ?string $navigationIcon  = 'heroicon-o-chart-bar';
    protected static ?string $navigationLabel = 'Dashboard de Vacaciones';
    protected static ?string $navigationGroup = 'Reportes';
    protected static ?string $title           = 'Control de Vacaciones por Periodo';
    protected static ?int    $navigationSort  = 7;
    protected static string  $view            = 'filament.pages.dashboard-vacaciones';

    // Filtro simple por query string (recarga completa, no Livewire) — el
    // contenido del reporte se genera con JS que escribe HTML dinámico
    // (tablas/barras), lo que rompe la detección de "elemento raíz único"
    // de Livewire si se maneja como formulario reactivo.
    public function fechaCorte(): string
    {
        return request()->query('fecha_corte', now()->toDateString());
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('excel')->label('Exportar Excel')->icon('heroicon-o-table-cells')->color('success')
                ->url(fn () => route('reportes.dashboard-vacaciones', ['formato' => 'xlsx'] + request()->query())),
            Action::make('pdf')->label('Exportar PDF')->icon('heroicon-o-document-arrow-down')->color('danger')
                ->url(fn () => route('reportes.dashboard-vacaciones', ['formato' => 'pdf'] + request()->query())),
        ];
    }

    protected function getViewData(): array
    {
        $corte = Carbon::parse($this->fechaCorte());

        $data = app(VacacionesPorPeriodoService::class)
            ->buildDashboardData(CompanyContext::get(), $corte);

        return ['data' => $data, 'fechaCorte' => $corte->toDateString()];
    }
}
