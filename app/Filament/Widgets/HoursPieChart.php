<?php

namespace App\Filament\Widgets;

use App\Models\AttendanceRecord;
use Leandrocfe\FilamentApexCharts\Widgets\ApexChartWidget;

class HoursPieChart extends ApexChartWidget
{
    protected static ?string $chartId = 'hoursPieChart';
    protected static ?string $heading = 'Horas Ordinarias vs Extras';
    protected static ?int $sort = 2;
    protected static ?string $pollingInterval = null;

    protected function getOptions(): array
    {
        $mes  = now()->month;
        $anio = now()->year;

        $records = AttendanceRecord::whereMonth('fecha', $mes)
            ->whereYear('fecha', $anio)
            ->selectRaw('
                SUM(horas_ordinarias) as ordinarias,
                SUM(horas_extra_diurnas + horas_extra_nocturnas) as extras
            ')
            ->first();

        $ordinarias = round($records->ordinarias ?? 0, 1);
        $extras     = round($records->extras ?? 0, 1);

        return [
            'chart' => [
                'type'   => 'pie',
                'height' => 300,
            ],
            'series' => [$ordinarias, $extras],
            'labels' => ['Horas Ordinarias', 'Horas Extra'],
            'colors' => ['#3b82f6', '#a855f7'],
            'legend' => [
                'position' => 'bottom',
            ],
            'dataLabels' => [
                'enabled' => true,
                'formatter' => 'function(val) { return val.toFixed(1) + "%" }',
            ],
            'tooltip' => [
                'y' => [
                    'formatter' => 'function(val) { return val + " hrs" }',
                ],
            ],
        ];
    }
}
