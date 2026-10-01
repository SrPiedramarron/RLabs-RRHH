<?php

namespace App\Services;

use App\Models\Employee;
use App\Models\VacacionHistorial;
use Carbon\Carbon;
use Illuminate\Support\Collection;

/**
 * Reporte "Control de Vacaciones por Periodo" — metodología legal completa
 * (DL 713), distinta y más detallada que VacacionesService::calcularSaldo()
 * (que lleva un saldo corrido simple, reseteado cada vez que se registra una
 * vacación). Aquí cada trabajador se descompone en periodos ANUALES fijos
 * desde su fecha de ingreso (aniversario a aniversario), cada uno con su
 * propio derecho ganado, goce imputado FIFO (el periodo más antiguo con
 * saldo se consume primero) y estado de vencimiento.
 *
 * Reglas (confirmadas con RRHH/contabilidad, oct. 2026 — mismas que ya
 * usaba Cielo en su control manual en Excel):
 *   - Periodo: de la fecha de ingreso/aniversario al siguiente aniversario.
 *     El derecho se adquiere al cumplir el año (se ganan los 30 días).
 *   - Imputación de goce: FIFO — se descuenta primero del periodo más
 *     antiguo con saldo.
 *   - Estado: "Gozada" (saldo 0), "Pendiente en plazo" (con saldo, dentro
 *     del año siguiente a la fecha en que se adquirió el derecho),
 *     "Vencida" (con saldo, pasado ese año — Art. 19 DL 713, revisar si
 *     corresponde indemnización vacacional), "En curso" (periodo todavía
 *     no cumplido).
 *   - Truncas: días ganados proporcionalmente del periodo EN CURSO (no
 *     forma parte de los días ganados, es solo informativo).
 */
class VacacionesPorPeriodoService
{
    const DIAS_POR_PERIODO = 30;
    const DIAS_POR_MES     = 2.5; // 30 / 12

    /**
     * Arma la data completa para el dashboard: resumen, periodos, matriz de
     * saldo por periodo y detalle de goce — de todos los trabajadores
     * activos de la empresa (o de todas, si $companyId es null).
     */
    public function buildDashboardData(?int $companyId, Carbon $corte): array
    {
        $empleados = Employee::where('active', true)
            ->whereNotNull('fecha_ingreso')
            ->when($companyId, fn ($q) => $q->where('company_id', $companyId))
            ->orderBy('apellidos')
            ->get();

        $resumen    = [];
        $periodoRows = [];
        $goceRows    = [];
        $idx = 1;

        foreach ($empleados as $empleado) {
            $periodos = $this->periodosDe($empleado, $corte);
            $nombre   = $empleado->apellidos . ', ' . $empleado->nombres;

            $completos = $periodos->where('completo', true);
            $enCurso   = $periodos->firstWhere('completo', false);

            $ganados = $completos->sum('ganados');
            $gozados = $completos->sum('gozados');
            $vencido = $completos->where('estado', 'Vencida')->sum('saldo');
            $enPlazo = $completos->where('estado', 'Pendiente en plazo')->sum('saldo');
            $saldo   = $vencido + $enPlazo;
            $truncas = $enCurso['ganados_informativo'] ?? 0;

            $programado = (float) VacacionHistorial::where('employee_id', $empleado->id)
                ->where('fecha_inicio', '>', $corte->toDateString())
                ->sum('dias');

            $resumen[] = [
                $idx++, $nombre, $empleado->fecha_ingreso->toDateString(),
                $completos->count(), $ganados, $gozados, $saldo, $vencido, $enPlazo,
                round($truncas, 2), $programado,
            ];

            foreach ($periodos as $p) {
                $periodoRows[] = [
                    $nombre,
                    $idx,
                    $p['label'],
                    $p['inicio']->year,
                    $p['completo'] ? $p['derecho']->toDateString() : null,
                    $p['completo'] ? $p['ganados'] : 0,
                    $p['gozados'],
                    $p['completo'] ? $p['saldo'] : 0,
                    $p['limite']?->toDateString(),
                    $p['estado'],
                ];
            }

            foreach (VacacionHistorial::where('employee_id', $empleado->id)->orderBy('fecha_inicio')->get() as $h) {
                $esProgramado = $h->fecha_inicio->gt($corte);
                $goceRows[] = [$nombre, $h->fecha_inicio->format('Y-m'), (int) $h->dias, $esProgramado ? 'Programado' : 'Gozado/aprobado'];
            }
        }

        [$saldoMatriz, $saldoCols] = $this->matrizSaldoPorPeriodo($empleados, $corte);

        return [
            'resumen' => $resumen,
            'periodo' => $periodoRows,
            'saldo'   => $saldoMatriz,
            'saldo_cols' => $saldoCols,
            'goce'    => $goceRows,
            'notas'   => $this->notas($corte),
        ];
    }

    /**
     * Descompone la antigüedad de un trabajador en periodos anuales fijos
     * desde su fecha de ingreso, con goce imputado FIFO desde su historial
     * real de vacaciones.
     *
     * @return Collection de arrays: label, inicio, derecho, limite,
     *   ganados, gozados, saldo, estado, completo, ganados_informativo
     */
    public function periodosDe(Employee $empleado, Carbon $corte): Collection
    {
        $inicio = Carbon::parse($empleado->fecha_ingreso)->startOfDay();

        $totalGozado = (float) VacacionHistorial::where('employee_id', $empleado->id)
            ->where('fecha_inicio', '<=', $corte->toDateString())
            ->sum('dias');

        $periodos = collect();

        while ($inicio->lte($corte)) {
            $derecho = $inicio->copy()->addYear();

            if ($derecho->gt($corte)) {
                // Periodo en curso (no cumplido aún) — informativo (truncas).
                $mesesCompletos = (int) floor($inicio->diffInMonths($corte));
                $fechaTrasCompletos = $inicio->copy()->addMonths($mesesCompletos);
                $diasRestantes = max(0, $fechaTrasCompletos->diffInDays($corte->copy()->addDay()));
                $ganadosInformativo = round($mesesCompletos * self::DIAS_POR_MES + ($diasRestantes / 30) * self::DIAS_POR_MES, 2);

                $periodos->push([
                    'label' => $inicio->format('Y') . '-' . $derecho->format('Y'),
                    'inicio' => $inicio, 'derecho' => null, 'limite' => null,
                    'ganados' => 0, 'gozados' => 0, 'saldo' => 0,
                    'estado' => 'En curso', 'completo' => false,
                    'ganados_informativo' => $ganadosInformativo,
                ]);
                break;
            }

            $limite = $derecho->copy()->addYear();
            $gozadosPeriodo = min(self::DIAS_POR_PERIODO, max(0, $totalGozado));
            $totalGozado -= $gozadosPeriodo;
            $saldo = self::DIAS_POR_PERIODO - $gozadosPeriodo;

            $estado = match (true) {
                $saldo <= 0           => 'Gozada',
                $corte->lte($limite)  => 'Pendiente en plazo',
                default               => 'Vencida',
            };

            $periodos->push([
                'label' => $inicio->format('Y') . '-' . $derecho->format('Y'),
                'inicio' => $inicio, 'derecho' => $derecho, 'limite' => $limite,
                'ganados' => self::DIAS_POR_PERIODO, 'gozados' => round($gozadosPeriodo, 2),
                'saldo' => round($saldo, 2), 'estado' => $estado, 'completo' => true,
            ]);

            $inicio = $derecho;
        }

        return $periodos;
    }

    private function matrizSaldoPorPeriodo(Collection $empleados, Carbon $corte): array
    {
        $porEmpleado = [];
        $columnas    = [];

        foreach ($empleados as $empleado) {
            $periodos = $this->periodosDe($empleado, $corte)->where('completo', true);
            $nombre   = $empleado->apellidos . ', ' . $empleado->nombres;
            $fila     = [];

            foreach ($periodos as $p) {
                if ($p['saldo'] > 0) {
                    $fila[$p['label']] = $p['saldo'];
                    $columnas[$p['label']] = true;
                }
            }

            $porEmpleado[$nombre] = $fila;
        }

        $cols = array_keys($columnas);
        sort($cols);

        $matriz = [];
        foreach ($porEmpleado as $nombre => $fila) {
            $total = array_sum($fila);
            $row = [$nombre, null];
            foreach ($cols as $c) {
                $row[] = $fila[$c] ?? 0;
            }
            $row[] = $total;
            $matriz[] = $row;
        }

        return [$matriz, $cols];
    }

    private function notas(Carbon $corte): array
    {
        return [
            ['Notas y parámetros'],
            ['Fecha de corte', $corte->toDateString()],
            ['Días de vacaciones por periodo', self::DIAS_POR_PERIODO],
            ['Último mes de goce considerado (mes en curso)', $corte->startOfMonth()->toDateString()],
            ['Criterios y observaciones'],
            ['Fuente', 'Datos en vivo del sistema (fecha de ingreso de la ficha del trabajador + historial de vacaciones registrado). Se recalcula cada vez que se abre este reporte.'],
            ['Periodo', 'Cada periodo va de la fecha de ingreso (o aniversario) al siguiente aniversario. El derecho se adquiere al cumplir el año y se ganan los 30 días completos del periodo.'],
            ['Imputación', 'Los días gozados (historial de vacaciones) se imputan al periodo más antiguo con saldo primero (FIFO).'],
            ['Estado', 'Gozada = saldo 0. Pendiente en plazo = con saldo y dentro del año siguiente a la fecha en que se adquirió el derecho. Vencida = con saldo y pasado ese año (revisar si corresponde indemnización vacacional, Art. 19 DL 713). En curso = periodo aún no cumplido.'],
            ['Truncas', 'Columna informativa: proporción de los 30 días del periodo EN CURSO, por mes completo transcurrido más el resto de días. No forma parte de los días ganados.'],
            ['Detalle de goce por mes', 'Cada vacación del historial se atribuye por completo al mes de su fecha de inicio (no se prorratea entre meses si cruza de uno a otro).'],
        ];
    }
}
