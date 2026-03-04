<?php

namespace App\Filament\Resources\StatisticsResource\Widgets;

use App\Models\AttendanceRecord;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Widget de resumen de tardanzas.
 * Colócalo también en el Dashboard editando:
 *   app/Filament/Pages/Dashboard.php → getWidgets()
 */
class TardanzaResumenWidget extends BaseWidget
{
    protected static ?string $pollingInterval = null;

    protected function getStats(): array
    {
        $user = Auth::user();
        $mesActual = now()->month;
        $anioActual = now()->year;

        $base = AttendanceRecord::query()
            ->whereMonth('fecha', $mesActual)
            ->whereYear('fecha', $anioActual)
            ->when($user->company_id, function ($q) use ($user) {
                $q->whereHas('employee', fn($e) => $e->where('company_id', $user->company_id));
            });

        // Total días con tardanza este mes
        $totalTardanzas = (clone $base)->where('minutos_tarde', '>', 0)->count();

        // Empleado más tardón este mes
        $masTardon = (clone $base)
            ->select('employee_id', DB::raw('SUM(minutos_tarde) as total'))
            ->where('minutos_tarde', '>', 0)
            ->groupBy('employee_id')
            ->orderByDesc('total')
            ->with('employee')
            ->first();

        $masTargonNombre = $masTardon?->employee?->nombres
            ? $masTardon->employee->nombres . ' ' . $masTardon->employee->apellidos
            : '—';
        $masTargonMinutos = $masTardon?->total ?? 0;

        // Total registros sin marcación de salida (últimos 30 días)
        $sinSalida = AttendanceRecord::query()
            ->whereNotNull('hora_entrada')
            ->whereNull('hora_salida')
            ->where('estado', '!=', 'ausente')
            ->whereDate('fecha', '>=', now()->subDays(30))
            ->when($user->company_id, function ($q) use ($user) {
                $q->whereHas('employee', fn($e) => $e->where('company_id', $user->company_id));
            })
            ->count();

        // Promedio de tardanza este mes (solo los que llegaron tarde)
        $promedioTardanza = (clone $base)
            ->where('minutos_tarde', '>', 0)
            ->avg('minutos_tarde');

        return [
            Stat::make('Tardanzas este mes', $totalTardanzas)
                ->description('Total registros con tardanza')
                ->descriptionIcon('heroicon-m-clock')
                ->color($totalTardanzas > 20 ? 'danger' : ($totalTardanzas > 10 ? 'warning' : 'success')),

            Stat::make('Promedio tardanza', round($promedioTardanza ?? 0) . ' min')
                ->description('Promedio de minutos tarde (quienes tardaron)')
                ->descriptionIcon('heroicon-m-chart-bar')
                ->color('warning'),

            Stat::make('Mayor acumulado', $masTargonNombre)
                ->description($masTargonMinutos . ' min acumulados este mes')
                ->descriptionIcon('heroicon-m-user')
                ->color('danger'),

            Stat::make('Sin marcar salida', $sinSalida)
                ->description('Registros sin hora de salida (30 días)')
                ->descriptionIcon('heroicon-m-arrow-right-on-rectangle')
                ->color($sinSalida > 0 ? 'warning' : 'success'),
        ];
    }
}
