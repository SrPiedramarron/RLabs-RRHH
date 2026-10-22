<?php

namespace App\Filament\Pages;

use App\Helpers\CompanyContext;
use App\Services\PuntualidadDashboardService;
use App\Support\EstadisticasAsistencia;
use Carbon\Carbon;
use Filament\Pages\Page;
use Illuminate\Support\Facades\DB;

class DashboardPuntualidad extends Page
{
    protected static ?string $navigationIcon  = 'heroicon-o-clock';
    protected static ?string $navigationLabel = 'Dashboard de Puntualidad';
    protected static ?string $navigationGroup = 'Reportes';
    protected static ?string $title           = 'Tardanzas y puntualidad';
    protected static ?int    $navigationSort  = 8;
    protected static string  $view            = 'filament.pages.dashboard-puntualidad';

    // Filtros por query string (recarga completa): el contenido lo dibuja JS, igual que el
    // dashboard de vacaciones.

    /** Cortes del 26 al 25 disponibles: clave 'YYYY-MM' = mes en que termina el corte. */
    private function cortes(): array
    {
        [, $actual] = EstadisticasAsistencia::corteMensual();
        $out = [];
        for ($i = 0; $i < 12; $i++) {
            $fin = $actual->copy()->subMonthsNoOverflow($i)->day(25);
            $ini = $fin->copy()->subMonthNoOverflow()->addDay();
            $out[$fin->format('Y-m')] = 'Corte ' . $fin->locale('es')->translatedFormat('F Y') . ' (' . $ini->format('d/m') . ' – ' . $fin->format('d/m') . ')';
        }

        return $out;
    }

    /** @return array{0: Carbon, 1: Carbon, 2: string} desde, hasta, clave elegida */
    private function rango(): array
    {
        $cortes = $this->cortes();
        $clave  = (string) request()->query('corte', array_key_first($cortes));

        if ($clave === 'custom') {
            $desde = $this->fecha(request()->query('desde')) ?? now()->startOfMonth();
            $hasta = $this->fecha(request()->query('hasta')) ?? now();
            if ($hasta->lt($desde)) {
                [$desde, $hasta] = [$hasta, $desde];
            }

            return [$desde->startOfDay(), $hasta->startOfDay(), 'custom'];
        }

        if (! isset($cortes[$clave])) {
            $clave = array_key_first($cortes);
        }
        $hasta = Carbon::createFromFormat('Y-m-d', $clave . '-25')->startOfDay();

        return [$hasta->copy()->subMonthNoOverflow()->addDay(), $hasta, $clave];
    }

    private function fecha($valor): ?Carbon
    {
        try {
            return $valor ? Carbon::parse($valor) : null;
        } catch (\Throwable) {
            return null;
        }
    }

    protected function getViewData(): array
    {
        [$desde, $hasta, $clave] = $this->rango();
        $companyId = CompanyContext::get();
        $sede = (int) request()->query('sede') ?: null;
        $area = (int) request()->query('area') ?: null;

        $data = app(PuntualidadDashboardService::class)->build($companyId, $desde, $hasta, $sede, $area);

        return [
            'data'    => $data,
            'cortes'  => $this->cortes(),
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
