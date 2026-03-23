<?php

namespace App\Filament\Widgets;

use App\Helpers\CompanyContext;
use App\Models\AttendanceRecord;
use Leandrocfe\FilamentApexCharts\Widgets\ApexChartWidget;
use Illuminate\Support\Facades\Auth;

class AttendancePieChart extends ApexChartWidget
{
    protected static ?string $chartId = 'attendancePieChart';
    protected static ?string $heading = 'Asistencia del Mes';
    protected static ?int $sort = 1;
    protected static ?string $pollingInterval = null;

    protected function getOptions(): array
    {
        $mes   = now()->month;
        $anio  = now()->year;

        $presentes = AttendanceRecord::where('estado', 'presente')
            ->when(CompanyContext::get(), fn($q) => $q->where('company_id', CompanyContext::get()))
            ->whereMonth('fecha', $mes)->whereYear('fecha', $anio)->count();

        $tardanzas = AttendanceRecord::where('estado', 'tarde')
            ->when(CompanyContext::get(), fn($q) => $q->where('company_id', CompanyContext::get()))
            ->whereMonth('fecha', $mes)->whereYear('fecha', $anio)->count();

        $ausentes = AttendanceRecord::where('estado', 'ausente')
            ->when(CompanyContext::get(), fn($q) => $q->where('company_id', CompanyContext::get()))
            ->whereMonth('fecha', $mes)->whereYear('fecha', $anio)->count();

        return [
            'chart' => [
                'type'    => 'pie',
                'height'  => 300,
            ],
            'series' => [$presentes, $tardanzas, $ausentes],
            'labels' => ['Presentes', 'Tardanzas', 'Ausentes'],
            'colors' => ['#22c55e', '#f59e0b', '#ef4444'],
            'legend' => [
                'position' => 'bottom',
            ],
            'dataLabels' => [
                'enabled' => true,
                'formatter' => 'function(val) { return val.toFixed(1) + "%" }',
            ],
            'tooltip' => [
                'y' => [
                    'formatter' => 'function(val) { return val + " registros" }',
                ],
            ],
        ];
    }
}
