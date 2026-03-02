<?php

namespace App\Filament\Widgets;

use App\Models\AttendanceRecord;
use App\Models\Holiday;
use Leandrocfe\FilamentApexCharts\Widgets\ApexChartWidget;

class WorkedDaysChart extends ApexChartWidget
{
    protected static ?string $chartId = 'workedDaysChart';
    protected static ?string $heading = 'Días Trabajados vs Hábiles';
    protected static ?int $sort = 3;
    protected static ?string $pollingInterval = null;

    protected function getOptions(): array
    {
        $mes       = now()->month;
        $anio      = now()->year;
        $inicio    = now()->startOfMonth();
        $fin       = now()->endOfMonth();

        // Días hábiles del mes (lunes a viernes, sin feriados)
        $feriados = Holiday::whereYear('fecha', $anio)
            ->whereMonth('fecha', $mes)
            ->pluck('fecha')
            ->map(fn($f) => $f->format('Y-m-d'))
            ->toArray();

        $diasHabiles = 0;
        $dia = $inicio->copy();
        while ($dia <= $fin) {
            if (!$dia->isWeekend() && !in_array($dia->format('Y-m-d'), $feriados)) {
                $diasHabiles++;
            }
            $dia->addDay();
        }

        // Días efectivamente trabajados (presente o tarde)
        $diasTrabajados = AttendanceRecord::whereIn('estado', ['presente', 'tarde'])
            ->whereMonth('fecha', $mes)
            ->whereYear('fecha', $anio)
            ->distinct('fecha')
            ->count('fecha');

        $diasNoTrabajados = max(0, $diasHabiles - $diasTrabajados);

        return [
            'chart' => [
                'type'   => 'donut',
                'height' => 300,
            ],
            'series' => [$diasTrabajados, $diasNoTrabajados],
            'labels' => ['Días Trabajados', 'Días No Trabajados'],
            'colors' => ['#0ea5e9', '#e2e8f0'],
            'legend' => [
                'position' => 'bottom',
            ],
            'plotOptions' => [
                'pie' => [
                    'donut' => [
                        'labels' => [
                            'show' => true,
                            'total' => [
                                'show'  => true,
                                'label' => 'Días Hábiles',
                                'formatter' => 'function(w) { return ' . $diasHabiles . ' }',
                            ],
                        ],
                    ],
                ],
            ],
            'tooltip' => [
                'y' => [
                    'formatter' => 'function(val) { return val + " días" }',
                ],
            ],
        ];
    }
}
