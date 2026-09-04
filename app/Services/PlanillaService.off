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

    // ── EPS (Sanitas Perú) ────────────────────────────────────────────────
    const IGV                   = 0.18;
    const CREDITO_EPS_PORCENTAJE = 0.25;
    const APORTE_EMPRESA_EPS     = 0.30; // el trabajador asume el 70% restante

    public function calcularPeriodo(int $companyId, string $periodo, array $bonosEspeciales = [], array $otrosDescuentos = [], array $adelantos = [], array $subsidiosEnfermedad = [], array $subsidiosMaternidad = []): Collection
    {
        [$year, $month] = explode('-', $periodo);

        $empleados = Employee::where('company_id', $companyId)
            ->where('active', true)
            ->whereNotNull('sueldo_base')
            ->where('sueldo_base', '>', 0)
            ->get();

        $liquidaciones = collect();

        DB::transaction(function () use ($empleados, $companyId, $periodo, $year, $month, $bonosEspeciales, $otrosDescuentos, $adelantos, $subsidiosEnfermedad, $subsidiosMaternidad, &$liquidaciones) {
            foreach ($empleados as $empleado) {
                $liquidacion = $this->calcularEmpleado(
                    $empleado,
                    $companyId,
                    $periodo,
                    (int) $year,
                    (int) $month,
                    $bonosEspeciales[$empleado->id] ?? 0,
                    $otrosDescuentos[$empleado->id] ?? 0,
                    $adelantos[$empleado->id] ?? 0,
                    $subsidiosEnfermedad[$empleado->id] ?? 0,
                    $subsidiosMaternidad[$empleado->id] ?? 0,
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
        float $otroDescuento = 0,
        float $adelanto = 0,
        float $subsidioEnfermedad = 0,
        float $subsidioMaternidad = 0,
    ): PlanillaLiquidacion {

        // Si no se pasó un valor nuevo (default 0), preservar lo que ya
        // estaba guardado — evita perder bonos/descuentos manuales cada vez
        // que se recalcula la planilla (ej. al editar un registro de
        // asistencia y volver a calcular). Para poner explícitamente en 0,
        // hay que borrar la liquidación o escribir 0 a mano en el Repeater.
        if ($bonoEspecial == 0.0 || $otroDescuento == 0.0 || $adelanto == 0.0 || $subsidioEnfermedad == 0.0 || $subsidioMaternidad == 0.0) {
            $existente = PlanillaLiquidacion::where('employee_id', $empleado->id)
                ->where('periodo', $periodo)
                ->first();

            if ($existente) {
                if ($bonoEspecial == 0.0 && $existente->bonos_especiales > 0) {
                    $bonoEspecial = (float) $existente->bonos_especiales;
                }
                if ($otroDescuento == 0.0 && $existente->otros_descuentos > 0) {
                    $otroDescuento = (float) $existente->otros_descuentos;
                }
                if ($adelanto == 0.0 && $existente->adelanto > 0) {
                    $adelanto = (float) $existente->adelanto;
                }
                if ($subsidioEnfermedad == 0.0 && $existente->subsidio_enfermedad > 0) {
                    $subsidioEnfermedad = (float) $existente->subsidio_enfermedad;
                }
                if ($subsidioMaternidad == 0.0 && $existente->subsidio_maternidad > 0) {
                    $subsidioMaternidad = (float) $existente->subsidio_maternidad;
                }
            }
        }


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
            $uploadIds = \App\Models\ComisionUpload::where('periodo', $periodo)
                ->where('company_id', $companyId)
                ->pluck('id');

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

        // Asignación familiar: monto fijo = 10% de la RMV, solo si el
        // empleado tiene el switch activado (hijos menores de 18, o hasta
        // 24 si estudian). Se recalcula sola si cambia RMV_2026.
        $asignacionFamiliar = $empleado->aplica_asignacion_familiar
            ? round(self::RMV_2026 * 0.10, 2)
            : 0.0;

        // Bono de movilidad: monto MÁXIMO mensual, prorrateado por asistencia
        // real. Si trabajó todos los días laborables del periodo, recibe el
        // máximo completo; si faltó, se prorratea hacia abajo (confirmado
        // con RRHH, ago 2026). NO entra a remuneración bruta — no afecta
        // EsSalud ni AFP/ONP. Sí afecta la base de 5ta categoría y el neto.
        $bonoMovilidad = $diasLaborables > 0
            ? round((floatval($empleado->movilidad_mensual_maxima) / $diasLaborables) * $diasTrabajados, 2)
            : 0.0;

        // Bono por encargatura: monto fijo mensual. A diferencia de movilidad,
        // SÍ afecta EsSalud y AFP/ONP (código PLAME 1007, confirmado con RRHH
        // y con el catálogo registrado en SUNAT para InProcess).
        $bonoEncargatura = round(floatval($empleado->bono_encargatura), 2);

        $bruto = $sueldoProporcional
               + $importeHEDiurnas
               + $importeHENocturnas
               + $comisiones
               + $asignacionFamiliar
               + $bonoEncargatura
               + $bonoEspecial
               - $descuentoTardanzas;

        $bruto = max(0, round($bruto, 2));

        // ── Descuento de pensión: ONP fijo, o desglose AFP real ────────────────
        $esAfp = str_starts_with($empleado->sistema_pensiones, 'afp_');

        // Subsidios EsSalud: NO afectan EsSalud ni ONP, pero SÍ afectan la
        // base de AFP (aporte, comisión, prima). Confirmado con RRHH.
        $subsidioTotal = $subsidioEnfermedad + $subsidioMaternidad;
        $baseAfp       = $bruto + ($esAfp ? $subsidioTotal : 0.0);

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

            // Prima de seguro y comisión se calculan sobre la base AFP
            // (incluye subsidios), con tope asegurable.
            $baseAsegurable = min($baseAfp, floatval($tasaAfp->tope_remuneracion_asegurable));

            $afpAporteObligatorio = round($baseAfp * floatval($tasaAfp->aporte_obligatorio), 2);
            // Comisión sobre flujo: SOLO para afiliados pre-2013 (Ley 29903).
            // Los post-2013 en "comisión mixta" no la pagan vía planilla —
            // la AFP cobra su parte directo de la cuenta del afiliado.
            $afpComisionFlujo = $empleado->aplica_comision_flujo_afp
                ? round($baseAsegurable * floatval($tasaAfp->comision_flujo), 2)
                : 0.0;
            $afpPrimaSeguro       = round($baseAsegurable * floatval($tasaAfp->prima_seguro), 2);

            $descuentoPension = $afpAporteObligatorio + $afpComisionFlujo + $afpPrimaSeguro;
            $tasaPension      = floatval($tasaAfp->aporte_obligatorio) + floatval($tasaAfp->comision_flujo) + floatval($tasaAfp->prima_seguro);
        } else {
            // ONP: subsidios NO afectan esta base — se calcula sobre $bruto
            // puro, sin sumar subsidios (a diferencia de AFP).
            $descuentoPension = round($bruto * self::TASA_ONP, 2);
        }

        $descuento5ta = 0.0;
        if ($empleado->aplica_5ta_categoria) {
            $detalle5ta   = app(\App\Services\Renta5taCalculator::class)->calcular(
                $empleado,
                $comisiones,
                $year,
                $month,
            );
            $descuento5ta = $detalle5ta['cuota_mensual'];
        }

        // ── EsSalud (empleador) — se calcula ANTES del neto porque el crédito
        // EPS depende de este monto. Base mínima es la RMV.
        $baseEssalud      = max($bruto, self::RMV_2026);
        $essaludEmpleador = round($baseEssalud * self::TASA_ESSALUD, 2);

        // ── EPS: solo si el trabajador tiene plan asignado (monto > 0).
        // Fórmula validada con RRHH (ago 2026):
        //   1) quitar IGV del costo del plan
        //   2) restar crédito EPS (25% del EsSalud que le correspondería)
        //   3) repartir el resto 30% empresa / 70% trabajador
        $epsCredito             = 0.0;
        $epsAporteEmpresa       = 0.0;
        $epsDescuentoTrabajador = 0.0;

        if (floatval($empleado->monto_eps_mensual_con_igv) > 0) {
            $importeEpsSinIgv = round(floatval($empleado->monto_eps_mensual_con_igv) / (1 + self::IGV), 2);
            $epsCredito       = round($essaludEmpleador * self::CREDITO_EPS_PORCENTAJE, 2);
            $importeEpsNeto   = max(0, $importeEpsSinIgv - $epsCredito);

            $epsAporteEmpresa       = round($importeEpsNeto * self::APORTE_EMPRESA_EPS, 2);
            $epsDescuentoTrabajador = round($importeEpsNeto * (1 - self::APORTE_EMPRESA_EPS), 2);
        }

        $totalDescuentos = $descuentoPension + $descuento5ta + $epsDescuentoTrabajador;
        $netoPagar       = round($bruto - $totalDescuentos + $bonoMovilidad - $otroDescuento - $adelanto + $subsidioTotal, 2);

        // Seguro vida ley: monto FIJO mensual (prima anual real ÷ 12, tal
        // como factura la aseguradora), NO un porcentaje calculado — cambia
        // solo cuando se renueva la póliza. Solo aplica desde 3 meses de
        // servicio (D.Leg 688). Confirmado con RRHH, ago 2026.
        $seguroVidaEmpleador = 0.0;
        if ($empleado->fecha_ingreso && Carbon::parse($empleado->fecha_ingreso)->diffInMonths(now()) >= 3) {
            $seguroVidaEmpleador = round(floatval($empleado->seguro_vida_mensual), 2);
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
                'asignacion_familiar'           => $asignacionFamiliar,
                'bono_movilidad'                => $bonoMovilidad,
                'bono_encargatura'               => $bonoEncargatura,
                'bonos_especiales'              => round($bonoEspecial, 2),
                'otros_descuentos'              => round($otroDescuento, 2),
                'adelanto'                       => round($adelanto, 2),
                'subsidio_enfermedad'            => round($subsidioEnfermedad, 2),
                'subsidio_maternidad'            => round($subsidioMaternidad, 2),
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
                'eps_credito'                    => $epsCredito,
                'eps_aporte_empresa'             => $epsAporteEmpresa,
                'eps_descuento_trabajador'       => $epsDescuentoTrabajador,
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
