<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\ComisionUploadResource;
use App\Filament\Resources\PlanillaLiquidacionResource;
use App\Filament\Resources\PlanillaQuincenaResource;
use App\Helpers\CompanyContext;
use App\Models\Company;
use App\Models\ComisionUpload;
use App\Models\Employee;
use App\Models\PlanillaLiquidacion;
use App\Models\PlanillaQuincena;
use Carbon\Carbon;
use Filament\Widgets\Widget;

class PlanillaMesWidget extends Widget
{
    protected static ?int $sort = -2;
    protected static string $view = 'filament.widgets.planilla-mes';
    protected int|string|array $columnSpan = 'full';

    public static function canView(): bool
    {
        return PlanillaLiquidacionResource::canViewAny();
    }

    /**
     * Periodo que se está trabajando: el del último corte (día 25) ya
     * cumplido — ej. el 2 de octubre se trabaja la planilla 09/2026
     * (asistencia del 26/08 al 25/09). Pedido: el corte es el 26 al 25.
     */
    private function periodo(): Carbon
    {
        $hoy = now();
        $corte = $hoy->day >= 26 ? $hoy->copy()->day(25) : $hoy->copy()->subMonthNoOverflow()->day(25);

        return $corte->startOfMonth();
    }

    protected function getViewData(): array
    {
        $companyId = CompanyContext::get();
        $company   = $companyId ? Company::find($companyId) : null;
        $fecha     = $this->periodo();
        $periodo   = $fecha->format('Y-m');

        $empleados = Employee::where('active', true)
            ->whereNotNull('sueldo_base')->where('sueldo_base', '>', 0)
            ->when($companyId, fn ($q) => $q->where('company_id', $companyId));
        $totalEmpleados = (clone $empleados)->count();
        $conComision    = (clone $empleados)->where('aplica_comision', true)->count();

        $liquidaciones = PlanillaLiquidacion::where('periodo', $periodo)
            ->when($companyId, fn ($q) => $q->where('company_id', $companyId));

        $calculadas = (clone $liquidaciones)->count();
        $firmadas   = (clone $liquidaciones)->whereNotNull('boleta_firmada_path')->where('boleta_firmada_path', '!=', '')->count();
        $totales    = (clone $liquidaciones)->selectRaw('SUM(neto_pagar) as neto, SUM(remuneracion_bruta + essalud_empleador) as costo')->first();

        $pasos = [];

        if ($conComision > 0) {
            $cargas = ComisionUpload::where('periodo', $periodo)
                ->when($companyId, fn ($q) => $q->where('company_id', $companyId))
                ->count();
            $pasos[] = [
                'titulo' => 'Comisiones cargadas',
                'detalle' => $cargas ? "{$cargas} carga(s) del periodo" : "{$conComision} trabajador(es) con comisión",
                'ok' => $cargas > 0,
                'url' => ComisionUploadResource::getUrl('index'),
            ];
        }

        if ($company?->pago_quincenal) {
            $quincenas = PlanillaQuincena::where('periodo', $periodo)
                ->where('company_id', $companyId)->count();
            $pasos[] = [
                'titulo' => 'Quincena calculada',
                'detalle' => "{$quincenas} de {$totalEmpleados} trabajadores",
                'ok' => $totalEmpleados > 0 && $quincenas >= $totalEmpleados,
                'url' => PlanillaQuincenaResource::getUrl('index'),
            ];
        }

        $pasos[] = [
            'titulo' => 'Planilla calculada',
            'detalle' => "{$calculadas} de {$totalEmpleados} trabajadores",
            'ok' => $totalEmpleados > 0 && $calculadas >= $totalEmpleados,
            'url' => PlanillaLiquidacionResource::getUrl('index'),
        ];

        $pasos[] = [
            'titulo' => 'Boletas firmadas',
            'detalle' => "{$firmadas} de {$calculadas} boletas",
            'ok' => $calculadas > 0 && $firmadas >= $calculadas,
            'url' => PlanillaLiquidacionResource::getUrl('index'),
        ];

        $meses = ['enero', 'febrero', 'marzo', 'abril', 'mayo', 'junio', 'julio', 'agosto', 'septiembre', 'octubre', 'noviembre', 'diciembre'];

        return [
            'periodoLabel' => ucfirst($meses[$fecha->month - 1]) . ' ' . $fecha->year,
            'rango'        => $fecha->copy()->subMonthNoOverflow()->day(26)->format('d/m') . ' al ' . $fecha->copy()->day(25)->format('d/m/Y'),
            'pasos'        => $pasos,
            'neto'         => (float) ($totales->neto ?? 0),
            'costo'        => (float) ($totales->costo ?? 0),
        ];
    }
}
