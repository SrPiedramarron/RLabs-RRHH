<?php

namespace App\Filament\Widgets;

use App\Filament\Pages\DashboardVacaciones;
use App\Filament\Resources\EmployeeResource;
use App\Filament\Resources\LiquidacionCeseResource;
use App\Filament\Resources\SolicitudResource;
use App\Helpers\CompanyContext;
use App\Models\Employee;
use App\Models\Solicitud;
use App\Services\VacacionesPorPeriodoService;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\Cache;

class AtencionRequeridaWidget extends BaseWidget
{
    protected static ?int $sort = -3;
    protected static ?string $pollingInterval = null;
    protected int|string|array $columnSpan = 'full';

    protected function getHeading(): ?string
    {
        return 'Requiere atención';
    }

    protected function getColumns(): int
    {
        return 4;
    }

    protected function getStats(): array
    {
        $companyId = CompanyContext::get();

        $solicitudes = Solicitud::where('estado', 'pendiente')
            ->when($companyId, fn ($q) => $q->where('company_id', $companyId))
            ->count();

        $contratos = Employee::where('active', true)
            ->whereNull('fecha_cese')
            ->whereBetween('fecha_fin_contrato', [now()->toDateString(), now()->addDays(30)->toDateString()])
            ->when($companyId, fn ($q) => $q->where('company_id', $companyId))
            ->count();

        // Ceses de los últimos 60 días sin liquidación calculada (los más
        // viejos suelen ser históricos de antes del sistema, ruido).
        $cesesSinLiquidar = Employee::whereNotNull('fecha_cese')
            ->where('fecha_cese', '>=', now()->subDays(60)->toDateString())
            ->whereNotExists(fn ($q) => $q->selectRaw(1)->from('liquidaciones_cese')->whereColumn('liquidaciones_cese.employee_id', 'employees.id'))
            ->when($companyId, fn ($q) => $q->where('company_id', $companyId))
            ->count();

        // Cálculo por periodo para todos los trabajadores: se cachea 10 min.
        $vencidas = Cache::remember("dash_vac_vencidas_" . ($companyId ?? 'all') . '_' . now()->toDateString(), 600, function () use ($companyId) {
            $data = app(VacacionesPorPeriodoService::class)->buildDashboardData($companyId, now());

            return [
                'trabajadores' => count(array_filter($data['resumen'], fn ($r) => $r[7] > 0)),
                'dias'         => array_sum(array_column($data['resumen'], 7)),
            ];
        });

        $stats = [
            Stat::make('Solicitudes pendientes', $solicitudes)
                ->description($solicitudes ? 'Por aprobar o rechazar' : 'Todo al día')
                ->descriptionIcon('heroicon-o-inbox-arrow-down')
                ->color($solicitudes ? 'warning' : 'success')
                ->url(SolicitudResource::getUrl('index', ['tableFilters[estado][value]' => 'pendiente']))
                ->extraAttributes(['data-visible' => SolicitudResource::canViewAny() ? '1' : '0']),

            Stat::make('Contratos por vencer', $contratos)
                ->description($contratos ? 'En los próximos 30 días' : 'Ninguno en 30 días')
                ->descriptionIcon('heroicon-o-clock')
                ->color($contratos ? 'warning' : 'success')
                ->url(EmployeeResource::getUrl('index'))
                ->extraAttributes(['data-visible' => EmployeeResource::canViewAny() ? '1' : '0']),

            Stat::make('Vacaciones vencidas', $vencidas['trabajadores'])
                ->description($vencidas['trabajadores'] ? "{$vencidas['dias']} días en riesgo" : 'Sin saldo vencido')
                ->descriptionIcon('heroicon-o-sun')
                ->color($vencidas['trabajadores'] ? 'danger' : 'success')
                ->url(DashboardVacaciones::getUrl())
                ->extraAttributes(['data-visible' => DashboardVacaciones::canAccess() ? '1' : '0']),

            Stat::make('Ceses sin liquidar', $cesesSinLiquidar)
                ->description($cesesSinLiquidar ? 'Últimos 60 días' : 'Ninguno pendiente')
                ->descriptionIcon('heroicon-o-arrow-right-start-on-rectangle')
                ->color($cesesSinLiquidar ? 'danger' : 'success')
                ->url(LiquidacionCeseResource::getUrl('index'))
                ->extraAttributes(['data-visible' => LiquidacionCeseResource::canViewAny() ? '1' : '0']),
        ];

        return array_values(array_filter($stats, fn ($s) => ($s->getExtraAttributes()['data-visible'] ?? '1') === '1'));
    }
}
