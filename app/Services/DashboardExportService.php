<?php

namespace App\Services;

use App\Support\EstadisticasAsistencia as Est;

/**
 * Convierte los datos de los dashboards (vacaciones y puntualidad) en una estructura neutra
 * que alimenta tanto el Excel como el PDF, para que ambos digan exactamente lo mismo:
 *   ['titulo', 'subtitulo', 'kpis' => [[etiqueta, valor], ...],
 *    'hojas' => [['titulo', 'columnas' => [...], 'filas' => [[...]], 'pdf' => bool], ...]]
 */
class DashboardExportService
{
    private const DIAS = ['Lunes', 'Martes', 'Miércoles', 'Jueves', 'Viernes', 'Sábado', 'Domingo'];

    public function puntualidad(array $d, string $empresa = '', string $filtros = ''): array
    {
        $k   = $d['kpi'];
        $dmy = fn ($f) => implode('/', array_reverse(explode('-', $f)));
        $pct = fn ($v) => $v === null ? '—' : $v;

        $colsMedidas = ['Puntual', 'Tarde', '% puntualidad', 'Tiempo tarde (dd:hh:mm)', 'Min. por tardanza', 'Faltas injust.'];
        $medidas = fn ($x) => [
            $x['p'], $x['t'], $pct($x['pct']), Est::ddhhmm($x['min']),
            $x['t'] ? round($x['min'] / $x['t'], 1) : '—', $x['aus'],
        ];
        $ordenPct = fn (array $a) => collect($a)->sortByDesc(fn ($x) => $x['pct'] ?? -1)->values()->all();

        return [
            'titulo'    => 'Tardanzas y puntualidad',
            'subtitulo' => trim($empresa . ' · Del ' . $dmy($d['periodo']['desde']) . ' al ' . $dmy($d['periodo']['hasta']) . ($filtros ? ' · ' . $filtros : '')),
            'nota'      => 'Puntualidad = días puntual ÷ (días puntual + días tarde). Meta: ' . $d['umbral'] . '%. No incluye personal exonerado de registro.',
            'kpis' => [
                ['% de puntualidad', $k['pct'] === null ? '—' : $k['pct'] . '%'],
                ['Días puntual', $k['p']],
                ['Días tarde', $k['t']],
                ['Tiempo tarde total (dd:hh:mm)', Est::ddhhmm($k['min'])],
                ['Trabajadores con marcaciones', $k['personas']],
                ['Con alguna tardanza', $k['con_tardanza']],
                ['Sin ninguna tardanza', $k['sin_tardanza']],
                ['Faltas injustificadas', $k['aus']],
            ],
            'hojas' => [
                ['titulo' => 'Por sede', 'pdf' => true,
                    'columnas' => array_merge(['Sede', 'Trabajadores'], $colsMedidas),
                    'filas' => array_map(fn ($x) => array_merge([$x['n'], $x['personas']], $medidas($x)), $ordenPct($d['sedes']))],
                ['titulo' => 'Por área', 'pdf' => true,
                    'columnas' => array_merge(['Área', 'Trabajadores'], $colsMedidas),
                    'filas' => array_map(fn ($x) => array_merge([$x['n'], $x['personas']], $medidas($x)), $ordenPct($d['areas']))],
                ['titulo' => 'Áreas por sede', 'pdf' => true,
                    'columnas' => ['Sede', 'Área', 'Puntual', 'Tarde', '% puntualidad'],
                    'filas' => array_map(fn ($x) => [$x['sede'], $x['area'], $x['p'], $x['t'], $pct($x['pct'])],
                        collect($d['matriz'])->sortBy([['sede', 'asc'], ['area', 'asc']])->values()->all())],
                ['titulo' => 'Por persona', 'pdf' => true,
                    'columnas' => array_merge(['Persona', 'Sede', 'Área'], $colsMedidas),
                    'filas' => array_map(fn ($x) => array_merge([$x['n'], $x['sede'], $x['area']], $medidas($x)),
                        collect($d['personas'])->sortBy(fn ($x) => $x['pct'] ?? 101)->values()->all())],
                ['titulo' => 'Por día de la semana', 'pdf' => true,
                    'columnas' => ['Día', 'Puntual', 'Tarde', '% puntualidad'],
                    'filas' => array_map(fn ($x) => [self::DIAS[$x['d']] ?? $x['d'], $x['p'], $x['t'], $pct($x['pct'])], $d['semana'])],
                ['titulo' => 'Por día', 'pdf' => false,
                    'columnas' => ['Fecha', 'Puntual', 'Tarde', '% puntualidad'],
                    'filas' => array_map(fn ($x) => [$dmy($x['f']), $x['p'], $x['t'], $pct($x['pct'])], $d['dias'])],
            ],
        ];
    }

    public function vacaciones(array $d, string $empresa = '', string $corte = ''): array
    {
        $resumen = $d['resumen'];
        $col = fn (int $i) => array_sum(array_map(fn ($r) => (float) $r[$i], $resumen));
        $dmy = fn ($f) => $f ? implode('/', array_reverse(explode('-', (string) $f))) : '';

        $cols  = $d['saldo_cols'];
        $nCols = count($cols);
        $saldo = $d['saldo'];

        return [
            'titulo'    => 'Control de vacaciones por periodo',
            'subtitulo' => trim($empresa . ' · Fecha de corte: ' . $dmy($corte)),
            'nota'      => 'Días ganados, gozados y saldo por periodo anual cumplido; las truncas son lo que va ganando del año en curso.',
            'kpis' => [
                ['Trabajadores', count($resumen)],
                ['Días ganados', round($col(4), 2)],
                ['Días gozados', round($col(5), 2)],
                ['Saldo pendiente', round($col(6), 2)],
                ['Saldo vencido', round($col(7), 2)],
                ['Saldo en plazo', round($col(8), 2)],
                ['Con saldo vencido', count(array_filter($resumen, fn ($r) => $r[7] > 0))],
                ['Días programados', round($col(10), 2)],
            ],
            'hojas' => [
                ['titulo' => 'Por trabajador', 'pdf' => true,
                    'columnas' => ['N°', 'Trabajador', 'Ingreso', 'Periodos cumplidos', 'Días ganados', 'Días gozados', 'Saldo', 'Vencido', 'En plazo', 'Truncas (en curso)', 'Programado'],
                    'filas' => array_map(fn ($r) => [$r[0], $r[1], $dmy($r[2]), $r[3], $r[4], $r[5], $r[6], $r[7], $r[8], $r[9], $r[10]], $resumen)],
                ['titulo' => 'Saldo por periodo', 'pdf' => true,
                    'columnas' => array_merge(['Trabajador'], $cols, ['Total']),
                    'filas' => array_map(fn ($r) => array_merge([$r[0]], array_slice($r, 2, $nCols), [$r[2 + $nCols] ?? 0]),
                        array_values(array_filter($saldo, fn ($r) => (float) ($r[2 + $nCols] ?? 0) > 0)))],
                ['titulo' => 'Por periodo', 'pdf' => false,
                    'columnas' => ['Trabajador', 'Periodo', 'Año', 'Fecha de derecho', 'Ganados', 'Gozados', 'Saldo', 'Fecha límite', 'Estado'],
                    'filas' => array_map(fn ($r) => [$r[0], $r[2], $r[3], $dmy($r[4]), $r[5], $r[6], $r[7], $dmy($r[8]), $r[9]], $d['periodo'])],
                ['titulo' => 'Goce', 'pdf' => false,
                    'columnas' => ['Trabajador', 'Mes', 'Días', 'Tipo'],
                    'filas' => $d['goce']],
                ['titulo' => 'Notas', 'pdf' => false,
                    'columnas' => ['Concepto', 'Valor'],
                    'filas' => array_map(fn ($r) => [$r[0] ?? '', is_scalar($r[1] ?? '') ? ($r[1] ?? '') : json_encode($r[1])], $d['notas'])],
            ],
        ];
    }
}
