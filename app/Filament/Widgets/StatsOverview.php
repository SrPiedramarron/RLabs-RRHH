<?php

namespace App\Filament\Widgets;

use App\Helpers\CompanyContext;
use App\Models\AttendanceRecord;
use App\Models\Employee;
use App\Models\Location;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class StatsOverview extends BaseWidget
{
    protected static ?int $sort = 1;

    protected function getStats(): array
    {
        $hoy = now()->toDateString();

        $presentes = AttendanceRecord::whereDate('fecha', $hoy)
            ->when(CompanyContext::get(), fn($q) => $q->where('company_id', CompanyContext::get()))
            ->where('estado', 'presente')
            ->count();

        $tardanzas = AttendanceRecord::whereDate('fecha', $hoy)
            ->when(CompanyContext::get(), fn($q) => $q->where('company_id', CompanyContext::get()))
            ->where('estado', 'tarde')
            ->count();

        $totalEmpleados = Employee::where('active', true)
            ->when(CompanyContext::get(), fn($q) => $q->where('company_id', CompanyContext::get()))
            ->where('exonerado_registro', false)
            ->count();

        $ausentes = $totalEmpleados - $presentes - $tardanzas;
        $ausentes = max(0, $ausentes);

        $horasExtraMes = AttendanceRecord::whereMonth('fecha', now()->month)
            ->whereYear('fecha', now()->year)
            ->selectRaw('SUM(horas_extra_diurnas + horas_extra_nocturnas) as total')
            ->value('total') ?? 0;

        $tardanzasMes = AttendanceRecord::whereMonth('fecha', now()->month)
            ->whereYear('fecha', now()->year)
            ->where('estado', 'tarde')
            ->count();

        return [
            Stat::make('Presentes Hoy', $presentes)
                ->description("{$totalEmpleados} empleados activos")
                ->descriptionIcon('heroicon-o-user-group')
                ->color('success'),

            Stat::make('Tardanzas Hoy', $tardanzas)
                ->description("Tardanzas este mes: {$tardanzasMes}")
                ->descriptionIcon('heroicon-o-clock')
                ->color($tardanzas > 0 ? 'warning' : 'success'),

            Stat::make('Ausentes Hoy', $ausentes)
                ->description("Sin marcar asistencia")
                ->descriptionIcon('heroicon-o-user-minus')
                ->color($ausentes > 0 ? 'danger' : 'success'),

            Stat::make('H. Extra este mes', round($horasExtraMes, 1))
                ->description("Horas acumuladas")
                ->descriptionIcon('heroicon-o-arrow-trending-up')
                ->color('info'),
        ];
    }
}