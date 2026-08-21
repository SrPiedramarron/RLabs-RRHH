<?php

namespace App\Services;

use App\Models\AfpTasa;
use App\Models\AttendanceRecord;
use App\Models\Employee;
use App\Models\PlanillaLiquidacion;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class PlanillaService
{
    // ── ONP: tasa fija por ley ─────────────────────────────────────────────
    const TASA_ONP = 0.1300;

    // ── Recargos horas extra (Ley 25593 Perú) ────────────────────────────────
    const RECARGO_HE_DIURNA   = 0.25; // primeras 2h del día: +25%
    const RECARGO_HE_NOCTURNA = 0.35; // horas adicionales:   +35%

    // ── Aportes de empleador ──────────────────────────────────────────────
    const TASA_ESSALUD       = 0.09;   // 9% sobre remuneración bruta
    const RMV_2026            = 1130;   // Remuneración Mínima Vital vigente — VERIFICAR antes de correr
    const TASA_SEGURO_VIDA_EMPLEADO = 0.0053; // 0.53% empleados (D.Leg 688). Obreros: 0.71%/1.46% — no soportado aún.

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

    public function calcularEmpleado(
        Employee $empleado,
        int $companyId,
        string $periodo,
        int $year,
        int $month,
        float $bonoEspecial = 0,
    ): PlanillaLiquidacion {

        $mesNombre     = $this->periodoANombre($periodo);
        $sueldo        = floatval($empleado->sueldo_base);
        $inicioPeriodo = Carbon::create($year, $month, 1)->startOfMonth();
        $finPeriodo    = Carbon::create($year, $month, 1)->endOfMonth();

        $diasLaborables = $this->contarDiasLaborables($inicioPeriodo, $finPeriodo);

        $asistencias = AttendanceRecord::where('employee_id', $empleado->id)
            ->whereBetween('fecha', [$inicioPeriodo->toDateString(), $finPeriodo->toDateString()])
            ->get();

        $diasTrabajados      = $asistencias->whereIn('estado', ['presente', 'tarde'])->count();
        $diasFalta           = $asistencias->where('estado', 'ausente')->where('justificado', false)->count();
        $diasJustificados    = $asistencias->where('justificado', true)->count();
        $totalMinutosTarde   = $asistencias->sum('minutos_tarde');
        $horasExtraDiurnas   = floatval($asistencias->sum('horas_extra_diurnas'));
        $horasExtraNocturnas = floatval($asistencias->sum('horas_extra_nocturnas'));

        $valorDia    = $sueldo / 30;
        $valorHora   = $sueldo / 30 / 8;
        $valorMinuto = $sueldo / 30 / 8 / 60;

        $sueldoProporcional = $sueldo - ($valorDia * $diasFalta);

        $importeHEDiurnas   = $horasExtraDiurnas   * $valorHora * (1 + self::RECARGO_HE_DIURNA);
        $importeHENocturnas = $horasExtraNocturnas * $valorHora * (1 + self::RECARGO_HE_NOCTURNA);

        // Comisiones del módulo (solo si el empleado aplica).
        // Filtra por employee_id real — antes sumaba TODO el periodo a cada
        // vendedor por igual, ahora cada uno recibe solo lo suyo.
        $comisiones = 0.0;
        if ($empleado->aplica_comision) {
            $uploadIds = \App\Models\ComisionUpload::where('periodo', $periodo)->pluck('id');

            if ($uploadIds->isNotEmpty()) {
                $comisiones = floatval(
                    \App\Models\ComisionDetalle::whereIn('comision_upload_id', $uploadIds)
                        ->where('employee_id', $empleado->id)
                        ->where('estado', 'cobrada')
                        ->sum('comision_calculada')
                );
            }
        }

        $descuentoTardanzas = round($valorMinuto * $totalMinutosTarde, 2);
        $descuentoFaltas    = round($valorDia    * $diasFalta, 2);

        $bruto = $sueldoProporcional
               + $importeHEDiurnas
               + $importeHENocturnas
               + $comisiones
               + $bonoEspecial
               - $descuentoTardanzas;

        $bruto = max(0, round($bruto, 2));

        // ── Descuento de pensión: ONP fijo, o desglose AFP real ────────────────
        $esAfp = str_starts_with($empleado->sistema_pensiones, 'afp_');

        $afpComisionFlujo    = 0.0;
        $afpPrimaSeguro      = 0.0;
        $afpAporteObligatorio = 0.0;
        $tasaPension          = self::TASA_ONP;
        $descuentoPension      = 0.0;

        if ($esAfp) {
            $tasaAfp = AfpTasa::vigentePara($empleado->sistema_pensiones);

            if (!$tasaAfp) {
                throw new \RuntimeException(
                    "No hay tasa AFP vigente configurada para '{$empleado->sistema_pensiones}'. " .
                    "Revisa la tabla afp_tasas antes de calcular la planilla."
                );
            }

            // Prima de seguro y comisión se calculan sobre el bruto, con tope asegurable
            $baseAsegurable = min($bruto, floatval($tasaAfp->tope_remuneracion_asegurable));

            $afpAporteObligatorio = round($bruto * floatval($tasaAfp->aporte_obligatorio), 2);
            $afpComisionFlujo     = round($baseAsegurable * floatval($tasaAfp->comision_flujo), 2);
            $afpPrimaSeguro       = round($baseAsegurable * floatval($tasaAfp->prima_seguro), 2);

            $descuentoPension = $afpAporteObligatorio + $afpComisionFlujo + $afpPrimaSeguro;
            $tasaPension      = floatval($tasaAfp->aporte_obligatorio) + floatval($tasaAfp->comision_flujo) + floatval($tasaAfp->prima_seguro);
        } else {
            $descuentoPension = round($bruto * self::TASA_ONP, 2);
        }

        $descuento5ta = 0.0;
        if ($empleado->aplica_5ta_categoria) {
            $uit2026       = 5350;
            $minimoMensual = ($uit2026 * 7) / 12;
            $baseImponible = max(0, $bruto - $minimoMensual);
            $descuento5ta  = round($baseImponible * 0.08, 2);
        }

        $totalDescuentos = $descuentoPension + $descuento5ta;
        $netoPagar       = round($bruto - $totalDescuentos, 2);

        // ── Aportes de empleador (no descuentan al trabajador) ─────────────────
        // EsSalud: base mínima es la RMV, aunque el sueldo real sea menor.
        $baseEssalud      = max($bruto, self::RMV_2026);
        $essaludEmpleador = round($baseEssalud * self::TASA_ESSALUD, 2);

        // Seguro vida ley: solo aplica desde 3 meses de servicio (D.Leg 688).
        $seguroVidaEmpleador = 0.0;
        if ($empleado->fecha_ingreso && Carbon::parse($empleado->fecha_ingreso)->diffInMonths(now()) >= 3) {
            $seguroVidaEmpleador = round($bruto * self::TASA_SEGURO_VIDA_EMPLEADO, 2);
        }

        $liquidacion = PlanillaLiquidacion::updateOrCreate(
            ['employee_id' => $empleado->id, 'periodo' => $periodo],
            [
                'company_id'                    => $companyId,
                'mes_nombre'                    => $mesNombre,
                'nombres'                       => $empleado->nombres,
                'apellidos'                     => $empleado->apellidos,
                'dni'                           => $empleado->dni,
                'cargo'                         => $empleado->cargo,
                'sueldo_base'                   => $sueldo,
                'sistema_pensiones'             => $empleado->sistema_pensiones,
                'aplica_5ta_categoria'          => $empleado->aplica_5ta_categoria,
                'dias_laborables'               => $diasLaborables,
                'dias_trabajados'               => $diasTrabajados,
                'dias_falta'                    => $diasFalta,
                'dias_justificados'             => $diasJustificados,
                'total_minutos_tarde'           => $totalMinutosTarde,
                'horas_extra_diurnas'           => $horasExtraDiurnas,
                'horas_extra_nocturnas'         => $horasExtraNocturnas,
                'sueldo_proporcional'           => round($sueldoProporcional, 2),
                'importe_horas_extra_diurnas'   => round($importeHEDiurnas, 2),
                'importe_horas_extra_nocturnas' => round($importeHENocturnas, 2),
                'comisiones'                    => round($comisiones, 2),
                'bonos_especiales'              => round($bonoEspecial, 2),
                'descuento_tardanzas'           => $descuentoTardanzas,
                'descuento_faltas'              => $descuentoFaltas,
                'remuneracion_bruta'            => $bruto,
                'porcentaje_pension'            => round($tasaPension, 4),
                'descuento_pension'             => round($descuentoPension, 2),
                'afp_comision_flujo'            => $afpComisionFlujo,
                'afp_prima_seguro'              => $afpPrimaSeguro,
                'afp_aporte_obligatorio'        => $afpAporteObligatorio,
                'descuento_5ta_categoria'       => $descuento5ta,
                'total_descuentos'              => round($totalDescuentos, 2),
                'neto_pagar'                    => $netoPagar,
                'essalud_empleador'             => $essaludEmpleador,
                'seguro_vida_empleador'         => $seguroVidaEmpleador,
                'calculado_por'                 => Auth::id(),
                'calculado_at'                  => now(),
            ]
        );

        return $liquidacion;
    }

    private function contarDiasLaborables(Carbon $inicio, Carbon $fin): int
    {
        $count  = 0;
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
