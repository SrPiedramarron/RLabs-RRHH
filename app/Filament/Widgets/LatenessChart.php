<?php

namespace App\Filament\Widgets;

use App\Helpers\CompanyContext;
use App\Models\AttendanceRecord;
use App\Models\Employee;
use Leandrocfe\FilamentApexCharts\Widgets\ApexChartWidget;

class LatenessChart extends ApexChartWidget
{
    protected static ?string $chartId = 'latenessChart';
    protected static ?string $heading = 'Top 10 — Tardanzas Acumuladas (minutos)';
    protected static ?int $sort = 4;
    protected static ?string $pollingInterval = null;
    protected int | string | array $columnSpan = 'full';

    protected function getOptions(): array
    {
        $mes  = now()->month;
        $anio = now()->year;

        $data = AttendanceRecord::with('employee')
            ->when(CompanyContext::get(), fn($q) => $q->where('company_id', CompanyContext::get()))
            ->whereMonth('fecha', $mes)
            ->whereYear('fecha', $anio)
            ->where('minutos_tarde', '>', 0)
            ->selectRaw('employee_id, SUM(minutos_tarde) as total_tardanza')
            ->groupBy('employee_id')
            ->orderByDesc('total_tardanza')
            ->limit(10)
            ->get();

        $nombres  = $data->map(fn($r) => $r->employee?->nombre_completo ?? 'N/A')->toArray();
        $minutos  = $data->map(fn($r) => (int) $r->total_tardanza)->toArray();

        return [
            'chart' => [
                'type'    => 'bar',
                'height'  => 300,
                'toolbar' => ['show' => false],
            ],
            'series' => [
                [
                    'name' => 'Minutos de tardanza',
                    'data' => $minutos,
                ],
            ],
            'xaxis' => [
                'categories' => $nombres,
                'labels'     => [
                    'rotate' => -30,
                    'style'  => ['fontSize' => '11px'],
                ],
            ],
            'yaxis' => [
                'title' => ['text' => 'Minutos'],
            ],
            'colors'      => ['#E30613'],
            'plotOptions' => [
                'bar' => [
                    'borderRadius'  => 4,
                    'columnWidth'   => '50%',
                ],
            ],
            'dataLabels' => ['enabled' => false],
            'tooltip'    => [
                'y' => [
                    'formatter' => 'function(val) { return val + " min" }',
                ],
            ],
        ];
    }
}