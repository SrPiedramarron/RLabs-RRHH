<?php

namespace App\Services;

use App\Models\AttendanceRecord;
use App\Models\ComisionDetalle;
use App\Models\Employee;
use App\Models\PlanillaLiquidacion;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class PlanillaService
{
    // ── Tasas de descuento por sistema de pensiones ───────────────────────────
    const TASAS_PENSION = [
        'onp'           => 0.1300,
        'afp_prima'     => 0.1023,
        'afp_integra'   => 0.1023,
        'afp_habitat'   => 0.1047,
        'afp_profuturo' => 0.1084,
    ];

    // ── Recargos horas extra (Ley 25593 Perú) ────────────────────────────────
    const RECARGO_HE_DIURNA   = 0.25; // primeras 2h del día: +25%
    const RECARGO_HE_NOCTURNA = 0.35; // horas adicionales:   +35%

    /**
     * Calcula la planilla de un periodo para todos los empleados activos
     * de una empresa. Si ya existe liquidación para un empleado/periodo,
     * la sobreescribe (reproceso).
     */
    public function calcularPeriodo(int $companyId, string $periodo, array $bonosEspeciales = []): Collection
    {
        [$year, $month] = explode('-', $periodo);

        $empleados = Employee::where('company_id', $companyId)
            ->where('active', true)
            ->whereNotNull('sueldo_base')
            ->where('sueldo_base', '>', 0)
            ->get();

        $liquidaciones = collect();

        DB::transaction(function () use ($empleados, $companyId, $periodo, $year, $month, $bonosEspeciales, &$liquidaciones) {
            foreach ($empleados as $empleado) {
                $liquidacion = $this->calcularEmpleado(
                    $empleado,
                    $companyId,
                    $periodo,
                    (int) $year,
                    (int) $month,
                    $bonosEspeciales[$empleado->id] ?? 0,
                );
                $liquidaciones->push($liquidacion);
            }
        });

        return $liquidaciones;
    }

    /**
     * Calcula la liquidación de un empleado para un periodo.
     */
    public function calcularEmpleado(
        Employee $empleado,
        int $companyId,
        string $periodo,
        int $year,
        int $month,
        float $bonoEspecial = 0,
    ): PlanillaLiquidacion {

        $mesNombre   = $this->periodoANombre($periodo);
        $sueldo      = floatval($empleado->sueldo_base);
        $inicioPeriodo = Carbon::create($year, $month, 1)->startOfMonth();
        $finPeriodo    = Carbon::create($year, $month, 1)->endOfMonth();

        // ── 1. Días laborables del mes (lun-vie, sin feriados) ────────────────
        $diasLaborables = $this->contarDiasLaborables($inicioPeriodo, $finPeriodo);

        // ── 2. Asistencia del mes ─────────────────────────────────────────────
        $asistencias = AttendanceRecord::where('employee_id', $empleado->id)
            ->whereBetween('fecha', [$inicioPeriodo->toDateString(), $finPeriodo->toDateString()])
            ->get();

        $diasTrabajados      = $asistencias->whereIn('estado', ['presente', 'tarde'])->count();
        $diasFalta           = $asistencias->where('estado', 'ausente')->where('justificado', false)->count();
        $diasJustificados    = $asistencias->where('justificado', true)->count();
        $totalMinutosTarde   = $asistencias->sum('minutos_tarde');
        $horasExtraDiurnas   = floatval($asistencias->sum('horas_extra_diurnas'));
        $horasExtraNocturnas = floatval($asistencias->sum('horas_extra_nocturnas'));

        // ── 3. Valores base para cálculo ──────────────────────────────────────
        $valorDia    = $sueldo / 30;
        $valorHora   = $sueldo / 30 / 8;
        $valorMinuto = $sueldo / 30 / 8 / 60;

        // ── 4. Ingresos ───────────────────────────────────────────────────────

        // Sueldo proporcional (descuenta días de falta)
        $sueldoProporcional = $sueldo - ($valorDia * $diasFalta);

        // Horas extra
        $importeHEDiurnas   = $horasExtraDiurnas   * $valorHora * (1 + self::RECARGO_HE_DIURNA);
        $importeHENocturnas = $horasExtraNocturnas  * $valorHora * (1 + self::RECARGO_HE_NOCTURNA);

        // Comisiones del módulo (solo si el empleado aplica)
        $comisiones = 0.0;
        if ($empleado->aplica_comision) {
            // Buscar en comision_detalles del periodo, por nombre del vendedor
            // El vendedor en el Excel se llama igual que el empleado (o "Oficina")
            // Por ahora tomamos el total del periodo para la empresa
            $uploadIds = \App\Models\ComisionUpload::where('periodo', $periodo)
                ->pluck('id');

            if ($uploadIds->isNotEmpty()) {
                $comisiones = floatval(
                    \App\Models\ComisionDetalle::whereIn('comision_upload_id', $uploadIds)
                        ->where('estado', 'cobrada')
                        ->sum('comision_calculada')
                );
            }
        }

        // ── 5. Descuentos ─────────────────────────────────────────────────────
        $descuentoTardanzas = round($valorMinuto * $totalMinutosTarde, 2);
        $descuentoFaltas    = round($valorDia    * $diasFalta, 2);

        // ── 6. Remuneración bruta ─────────────────────────────────────────────
        $bruto = $sueldoProporcional
               + $importeHEDiurnas
               + $importeHENocturnas
               + $comisiones
               + $bonoEspecial
               - $descuentoTardanzas;
        // Nota: descuento_faltas ya está en sueldo_proporcional

        $bruto = max(0, round($bruto, 2));

        // ── 7. Descuentos de ley ──────────────────────────────────────────────
        $tasaPension      = self::TASAS_PENSION[$empleado->sistema_pensiones] ?? 0.13;
        $descuentoPension = round($bruto * $tasaPension, 2);

        // 5ta categoría (simplificado: 8% sobre el exceso de 7 UIT anuales)
        // UIT 2026 = S/ 5,350 → 7 UIT = S/ 37,450 anuales = S/ 3,120.83 mensual
        $descuento5ta = 0.0;
        if ($empleado->aplica_5ta_categoria) {
            $uit2026          = 5350;
            $minimoMensual    = ($uit2026 * 7) / 12;
            $baseImponible    = max(0, $bruto - $minimoMensual);
            $descuento5ta     = round($baseImponible * 0.08, 2); // tramo básico 8%
        }

        // ── 8. Totales ────────────────────────────────────────────────────────
        $totalDescuentos = $descuentoPension + $descuento5ta;
        $netoPagar       = round($bruto - $totalDescuentos, 2);

        // ── 9. Guardar (upsert por employee_id + periodo) ─────────────────────
        $liquidacion = PlanillaLiquidacion::updateOrCreate(
            ['employee_id' => $empleado->id, 'periodo' => $periodo],
            [
                'company_id'                  => $companyId,
                'mes_nombre'                  => $mesNombre,
                'nombres'                     => $empleado->nombres,
                'apellidos'                   => $empleado->apellidos,
                'dni'                         => $empleado->dni,
                'cargo'                       => $empleado->cargo,
                'sueldo_base'                 => $sueldo,
                'sistema_pensiones'           => $empleado->sistema_pensiones,
                'aplica_5ta_categoria'        => $empleado->aplica_5ta_categoria,
                'dias_laborables'             => $diasLaborables,
                'dias_trabajados'             => $diasTrabajados,
                'dias_falta'                  => $diasFalta,
                'dias_justificados'           => $diasJustificados,
                'total_minutos_tarde'         => $totalMinutosTarde,
                'horas_extra_diurnas'         => $horasExtraDiurnas,
                'horas_extra_nocturnas'       => $horasExtraNocturnas,
                'sueldo_proporcional'         => round($sueldoProporcional, 2),
                'importe_horas_extra_diurnas' => round($importeHEDiurnas, 2),
                'importe_horas_extra_nocturnas' => round($importeHENocturnas, 2),
                'comisiones'                  => round($comisiones, 2),
                'bonos_especiales'            => round($bonoEspecial, 2),
                'descuento_tardanzas'         => $descuentoTardanzas,
                'descuento_faltas'            => $descuentoFaltas,
                'remuneracion_bruta'          => $bruto,
                'porcentaje_pension'          => $tasaPension,
                'descuento_pension'           => $descuentoPension,
                'descuento_5ta_categoria'     => $descuento5ta,
                'total_descuentos'            => $totalDescuentos,
                'neto_pagar'                  => $netoPagar,
                'calculado_por'               => Auth::id(),
                'calculado_at'                => now(),
            ]
        );

        return $liquidacion;
    }

    /**
     * Cuenta días laborables (lunes a viernes) en un rango.
     * TODO: integrar tabla de feriados cuando esté disponible.
     */
    private function contarDiasLaborables(Carbon $inicio, Carbon $fin): int
    {
        $count = 0;
        $period = CarbonPeriod::create($inicio, $fin);
        foreach ($period as $date) {
            if (!$date->isWeekend()) {
                $count++;
            }
        }
        return $count;
    }

    private function periodoANombre(string $periodo): string
    {
        $meses = [
            '01' => 'ENERO', '02' => 'FEBRERO', '03' => 'MARZO',
            '04' => 'ABRIL', '05' => 'MAYO',    '06' => 'JUNIO',
            '07' => 'JULIO', '08' => 'AGOSTO',  '09' => 'SEPTIEMBRE',
            '10' => 'OCTUBRE', '11' => 'NOVIEMBRE', '12' => 'DICIEMBRE',
        ];
        [$year, $month] = explode('-', $periodo);
        return ($meses[$month] ?? $month) . ' ' . $year;
    }
}
