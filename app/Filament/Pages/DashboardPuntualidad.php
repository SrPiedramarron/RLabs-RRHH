<?php

namespace App\Filament\Pages;

use App\Helpers\CompanyContext;
use App\Services\PuntualidadDashboardService;
use App\Support\EstadisticasAsistencia;
use Carbon\Carbon;
use Filament\Actions\Action;
use Filament\Pages\Page;
use Illuminate\Support\Facades\DB;

class DashboardPuntualidad extends Page
{
    use \BezhanSalleh\FilamentShield\Traits\HasPageShield;

    protected static ?string $navigationIcon  = 'heroicon-o-clock';
    protected static ?string $navigationLabel = 'Dashboard de Puntualidad';
    protected static ?string $navigationGroup = 'Reportes';
    protected static ?string $title           = 'Tardanzas y puntualidad';
    protected static ?int    $navigationSort  = 8;
    protected static string  $view            = 'filament.pages.dashboard-puntualidad';

    // Filtros por query string (recarga completa): el contenido lo dibuja JS, igual que el
    // dashboard de vacaciones.

    protected function getHeaderActions(): array
    {
        return [
            Action::make('excel')->label('Exportar Excel')->icon('heroicon-o-table-cells')->color('success')
                ->url(fn () => route('reportes.dashboard-puntualidad', ['formato' => 'xlsx'] + request()->query())),
            Action::make('pdf')->label('Exportar PDF')->icon('heroicon-o-document-arrow-down')->color('danger')
                ->url(fn () => route('reportes.dashboard-puntualidad', ['formato' => 'pdf'] + request()->query())),
        ];
    }

    protected function getViewData(): array
    {
        [$desde, $hasta, $clave] = EstadisticasAsistencia::rangoDeRequest(request());
        $companyId = CompanyContext::get();
        $sede = (int) request()->query('sede') ?: null;
        $area = (int) request()->query('area') ?: null;

        $data = app(PuntualidadDashboardService::class)->build($companyId, $desde, $hasta, $sede, $area);

        return [
            'data'    => $data,
            'cortes'  => EstadisticasAsistencia::cortesDisponibles(),
            'clave'   => $clave,
            'desde'   => $desde->toDateString(),
            'hasta'   => $hasta->toDateString(),
            'sede'    => $sede,
            'area'    => $area,
            'sedes'   => DB::table('locations')->when($companyId, fn ($q) => $q->where('company_id', $companyId))->orderBy('nombre')->pluck('nombre', 'id')->all(),
            'areas'   => DB::table('departments')->when($companyId, fn ($q) => $q->where('company_id', $companyId))->orderBy('nombre')->pluck('nombre', 'id')->all(),
        ];
    }
}
