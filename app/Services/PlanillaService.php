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
        $diasVacaciones      = $asistencias->where('estado', 'vacaciones')->count();
        $diasJustificados    = $asistencias->where('justificado', true)->count();
        $totalMinutosTarde   = $asistencias->sum('minutos_tarde');
        $horasExtraDiurnas   = floatval($asistencias->sum('horas_extra_diurnas'));
        $horasExtraNocturnas = floatval($asistencias->sum('horas_extra_nocturnas'));

        $valorDia    = $sueldo / 30;
        $valorHora   = $sueldo / 30 / 8;
        $valorMinuto = $sueldo / 30 / 8 / 60;

        // El sueldo de los días de vacaciones se saca de aquí (0121) y se
        // mueve a 0118, para no duplicar — confirmado con RRHH.
        $sueldoProporcional = $sueldo - ($valorDia * $diasFalta) - ($valorDia * $diasVacaciones);

        // Remuneración vacacional (0118): sueldo de esos días (mismo valorDia
        // de arriba) + promedio de COMISIONES de los últimos 6 meses ANTES
        // del mes actual, prorrateado por los días de vacaciones tomados.
        // Confirmado con RRHH: "x" = solo comisiones, no otras variables.
        $sueldoVacacional     = round($valorDia * $diasVacaciones, 2);
        $comisionesVacaciones = 0.0;

        if ($diasVacaciones > 0) {
            $sumaComisiones6m = 0.0;
            for ($i = 1; $i <= 6; $i++) {
                $m = $month - $i;
                $y = $year;
                while ($m <= 0) { $m += 12; $y -= 1; }
                $periodoHist = sprintf('%04d-%02d', $y, $m);

                $montoMes = (float) \App\Models\IngresoHistorico5ta::where('employee_id', $empleado->id)
                    ->where('periodo', $periodoHist)
                    ->where('concepto', 'COMISIONES')
                    ->value('monto');

                if ($montoMes <= 0) {
                    $montoMes = (float) \App\Models\PlanillaLiquidacion::where('employee_id', $empleado->id)
                        ->where('periodo', $periodoHist)
                        ->value('comisiones');
                }

                $sumaComisiones6m += $montoMes;
            }

            $promedioDiarioComisiones = $sumaComisiones6m / 6 / 30;
            $comisionesVacaciones     = round($promedioDiarioComisiones * $diasVacaciones, 2);
        }

        $vacacionesTotal = round($sueldoVacacional + $comisionesVacaciones, 2);

        // Si el trabajador compensa sus horas extra con tiempo libre (sale
        // tarde un día, entra tarde otro), no se le paga — las horas quedan
        // igual registradas arriba (horas_extra_diurnas/nocturnas) para
        // referencia, solo se deja en 0 el importe a pagar.
        $importeHEDiurnas   = $empleado->compensa_horas_extras ? 0.0 : $horasExtraDiurnas   * $valorHora * (1 + self::RECARGO_HE_DIURNA);
        $importeHENocturnas = $empleado->compensa_horas_extras ? 0.0 : $horasExtraNocturnas * $valorHora * (1 + self::RECARGO_HE_NOCTURNA);

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
               + $vacacionesTotal
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
            $calculadora5ta = app(\App\Services\Renta5taCalculator::class);

            $detalle5ta = $empleado->aplica_comision
                ? $calculadora5ta->calcular($empleado, $comisiones, $year, $month)
                : $calculadora5ta->calcularNoComisionado($empleado, $sueldo + $asignacionFamiliar, $year, $month);

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

        // Adelanto de quincena (día 15): ya se le depositó al trabajador,
        // se resta del neto de fin de mes para no pagarlo dos veces.
        // Confirmado con RRHH (set. 2026, versión definitiva): la quincena
        // es un adelanto a cuenta calculado con sueldo_base + asignación
        // familiar (Opción A) — la liquidación mensual sigue calculando
        // TODO (horas extra, comisiones, vacaciones, AFP, 5ta, etc.) igual
        // que siempre, y a ese resultado se le resta lo ya adelantado.
        $adelantoQuincena = (float) \App\Models\PlanillaQuincena::where('employee_id', $empleado->id)
            ->where('periodo', $periodo)
            ->value('neto_pagar');

        $netoPagar       = round($bruto - $totalDescuentos + $bonoMovilidad - $otroDescuento - $adelanto - $adelantoQuincena + $subsidioTotal, 2);

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
                'dias_vacaciones'                => $diasVacaciones,
                'vacaciones'                     => $vacacionesTotal,
                'bonos_especiales'              => round($bonoEspecial, 2),
                'otros_descuentos'              => round($otroDescuento, 2),
                'adelanto'                       => round($adelanto, 2),
                'adelanto_quincena'              => round($adelantoQuincena, 2),
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

    // ─────────────────────────────────────────────────────────────────────
    // QUINCENA (adelanto del día 15)
    //
    // Confirmado con RRHH — versión DEFINITIVA (set. 2026): InProcess,
    // Quantum y Anthia pagan quincenal. La quincena toma en cuenta:
    //   - sueldo_base
    //   - asignación familiar (si aplica — mismo monto fijo que usa la
    //     mensual: RMV_2026 × 10%)
    //   - AFP/ONP sobre esa base
    //   - Renta 5ta sobre esa base (calcularNoComisionado, igual patrón
    //     que usa la mensual para no-comisionados)
    // NO toma en cuenta: comisiones, tardanzas, horas extra.
    // Todo ese cálculo (base - AFP/ONP - 5ta) se divide entre 2.
    // El resultado se resta después en la liquidación mensual de fin de
    // mes (ver $adelantoQuincena en calcularEmpleado más arriba).
    // ─────────────────────────────────────────────────────────────────────

    public function calcularPeriodoQuincena(int $companyId, string $periodo): Collection
    {
        [$year, $month] = explode('-', $periodo);

        $company = \App\Models\Company::find($companyId);

        if (! $company || ! $company->pago_quincenal) {
            throw new \RuntimeException(
                "La empresa seleccionada no tiene activado 'Paga quincenal'. Actívalo en su ficha (Configuración → Empresas) antes de calcular."
            );
        }

        $empleados = Employee::where('company_id', $companyId)
            ->where('active', true)
            ->whereNotNull('sueldo_base')
            ->where('sueldo_base', '>', 0)
            ->get();

        $quincenas = collect();

        DB::transaction(function () use ($empleados, $companyId, $periodo, $year, $month, &$quincenas) {
            foreach ($empleados as $empleado) {
                $quincenas->push(
                    $this->calcularQuincenaEmpleado($empleado, $companyId, $periodo, (int) $year, (int) $month)
                );
            }
        });

        return $quincenas;
    }

    public function calcularQuincenaEmpleado(
        Employee $empleado,
        int $companyId,
        string $periodo,
        int $year,
        int $month,
    ): \App\Models\PlanillaQuincena {
        $mesNombre = $this->periodoANombre($periodo);
        $sueldo    = floatval($empleado->sueldo_base);

        $asignacionFamiliar = $empleado->aplica_asignacion_familiar
            ? round(self::RMV_2026 * 0.10, 2)
            : 0.0;

        $baseQuincenal = round($sueldo + $asignacionFamiliar, 2);
        $mitadBase     = round($baseQuincenal / 2, 2);

        // ── AFP/ONP sobre la base quincenal completa, luego dividido ────────
        // (mismo resultado que calcularlo directo sobre mitadBase, ya que
        // los % son lineales — se calcula así para reusar la estructura de
        // AfpTasa igual que la mensual).
        $esAfp = str_starts_with($empleado->sistema_pensiones, 'afp_');

        $afpComisionFlujo     = 0.0;
        $afpPrimaSeguro       = 0.0;
        $afpAporteObligatorio = 0.0;
        $tasaPension          = self::TASA_ONP;
        $descuentoPension     = 0.0;

        if ($esAfp) {
            $tasaAfp = AfpTasa::vigentePara($empleado->sistema_pensiones);

            if (!$tasaAfp) {
                throw new \RuntimeException(
                    "No hay tasa AFP vigente configurada para '{$empleado->sistema_pensiones}'. " .
                    "Revisa la tabla afp_tasas antes de calcular la quincena."
                );
            }

            $baseAsegurable = min($baseQuincenal, floatval($tasaAfp->tope_remuneracion_asegurable));

            $afpAporteObligatorio = round($baseQuincenal * floatval($tasaAfp->aporte_obligatorio) / 2, 2);
            $afpComisionFlujo = $empleado->aplica_comision_flujo_afp
                ? round($baseAsegurable * floatval($tasaAfp->comision_flujo) / 2, 2)
                : 0.0;
            $afpPrimaSeguro = round($baseAsegurable * floatval($tasaAfp->prima_seguro) / 2, 2);

            $descuentoPension = $afpAporteObligatorio + $afpComisionFlujo + $afpPrimaSeguro;
            $tasaPension      = floatval($tasaAfp->aporte_obligatorio) + floatval($tasaAfp->comision_flujo) + floatval($tasaAfp->prima_seguro);
        } else {
            $descuentoPension = round($baseQuincenal * self::TASA_ONP / 2, 2);
        }

        // ── Renta 5ta sobre la base quincenal (sin comisiones), dividida ────
        $descuento5ta = 0.0;
        if ($empleado->aplica_5ta_categoria) {
            $detalle5ta = app(\App\Services\Renta5taCalculator::class)
                ->calcularNoComisionado($empleado, $baseQuincenal, $year, $month);

            $descuento5ta = round($detalle5ta['cuota_mensual'] / 2, 2);
        }

        $netoPagar = round($mitadBase - $descuentoPension - $descuento5ta, 2);

        return \App\Models\PlanillaQuincena::updateOrCreate(
            ['employee_id' => $empleado->id, 'periodo' => $periodo],
            [
                'company_id'              => $companyId,
                'mes_nombre'              => $mesNombre,
                'nombres'                 => $empleado->nombres,
                'apellidos'               => $empleado->apellidos,
                'dni'                     => $empleado->dni,
                'cargo'                   => $empleado->cargo,
                'sueldo_base'             => $sueldo,
                'asignacion_familiar'     => $asignacionFamiliar,
                'base_quincenal'          => $mitadBase,
                'sistema_pensiones'       => $empleado->sistema_pensiones,
                'porcentaje_pension'      => round($tasaPension, 4),
                'descuento_pension'       => round($descuentoPension, 2),
                'afp_comision_flujo'      => $afpComisionFlujo,
                'afp_prima_seguro'        => $afpPrimaSeguro,
                'afp_aporte_obligatorio'  => $afpAporteObligatorio,
                'aplica_5ta_categoria'    => $empleado->aplica_5ta_categoria,
                'descuento_5ta_categoria' => round($descuento5ta, 2),
                'neto_pagar'              => $netoPagar,
                'calculado_por'           => Auth::id(),
                'calculado_at'            => now(),
            ]
        );
    }

    /**
     * Gratificación legal (Fiestas Patrias / Navidad) + bonificación
     * extraordinaria 9% (Ley 29351).
     *
     * Reglas confirmadas con RRHH (set. 2026):
     *  - Remuneración computable = sueldo base + asignación familiar +
     *    promedio de comisiones de los últimos 6 meses + promedio de
     *    horas extra de los últimos 6 meses.
     *  - Comisiones y horas extra SOLO se promedian si el trabajador
     *    tuvo en al menos 3 de esos 6 meses (regla legal de remuneración
     *    variable/imprecisa) — si tuvo menos de 3, no se suman.
     *  - Si no completó el semestre, se prorratea: remuneración
     *    computable ÷ 6 × meses computables.
     *  - Bonificación extraordinaria = 9% del monto de gratificación
     *    (flat, sin distinguir afiliación EPS — igual que el resto del
     *    sistema).
     */
    public function calcularPeriodoGratificacion(int $companyId, string $tipo, int $anio): Collection
    {
        if (!in_array($tipo, ['julio', 'diciembre'], true)) {
            throw new \InvalidArgumentException("Tipo de gratificación inválido: {$tipo}");
        }

        $empleados = Employee::where('company_id', $companyId)
            ->where('active', true)
            ->whereNotNull('sueldo_base')
            ->where('sueldo_base', '>', 0)
            ->get();

        $gratificaciones = collect();

        DB::transaction(function () use ($empleados, $companyId, $tipo, $anio, &$gratificaciones) {
            foreach ($empleados as $empleado) {
                $g = $this->calcularGratificacionEmpleado($empleado, $companyId, $tipo, $anio);
                if ($g) {
                    $gratificaciones->push($g);
                }
            }
        });

        return $gratificaciones;
    }

    public function calcularGratificacionEmpleado(
        Employee $empleado,
        int $companyId,
        string $tipo,
        int $anio,
    ): ?\App\Models\Gratificacion {
        $mesesSemestre = $tipo === 'julio'
            ? range(1, 6)
            : range(7, 12);

        $periodoPago = $tipo === 'julio'
            ? sprintf('%04d-07', $anio)
            : sprintf('%04d-12', $anio);

        $fechaIngreso = $empleado->fecha_ingreso ? Carbon::parse($empleado->fecha_ingreso) : null;
        $fechaCese    = $empleado->fecha_cese ? Carbon::parse($empleado->fecha_cese) : null;

        $mesesComputables    = 0;
        $mesesConComisiones  = 0;
        $sumaComisiones      = 0.0;
        $mesesConHorasExtra  = 0;
        $sumaHorasExtra      = 0.0;

        foreach ($mesesSemestre as $m) {
            $inicioMes = Carbon::create($anio, $m, 1)->startOfMonth();
            $finMes    = Carbon::create($anio, $m, 1)->endOfMonth();

            $activoEseMes = (!$fechaIngreso || $fechaIngreso->lte($finMes))
                && (!$fechaCese || $fechaCese->gte($inicioMes));

            if ($activoEseMes) {
                $mesesComputables++;
            }

            $periodoMes = sprintf('%04d-%02d', $anio, $m);
            $liquidacion = PlanillaLiquidacion::where('employee_id', $empleado->id)
                ->where('periodo', $periodoMes)
                ->first();

            if (!$liquidacion) {
                continue;
            }

            if ((float) $liquidacion->comisiones > 0) {
                $mesesConComisiones++;
                $sumaComisiones += (float) $liquidacion->comisiones;
            }

            $horasExtraMes = (float) $liquidacion->importe_horas_extra_diurnas + (float) $liquidacion->importe_horas_extra_nocturnas;
            if ($horasExtraMes > 0) {
                $mesesConHorasExtra++;
                $sumaHorasExtra += $horasExtraMes;
            }
        }

        if ($mesesComputables === 0) {
            return null; // no trabajó ni un día del semestre — no le corresponde
        }

        $asignacionFamiliar = $empleado->aplica_asignacion_familiar
            ? round(self::RMV_2026 * 0.10, 2)
            : 0.0;

        // Regla legal: solo se promedia si hubo en >= 3 de los 6 meses.
        $promedioComisiones = $mesesConComisiones >= 3 ? round($sumaComisiones / 6, 2) : 0.0;
        $promedioHorasExtra = $mesesConHorasExtra >= 3 ? round($sumaHorasExtra / 6, 2) : 0.0;

        $remuneracionComputable = round(
            floatval($empleado->sueldo_base) + $asignacionFamiliar + $promedioComisiones + $promedioHorasExtra,
            2
        );

        $montoGratificacion = round($remuneracionComputable / 6 * $mesesComputables, 2);
        $bonificacionExtraordinaria = round($montoGratificacion * 0.09, 2);
        $montoTotal = round($montoGratificacion + $bonificacionExtraordinaria, 2);

        return \App\Models\Gratificacion::updateOrCreate(
            ['employee_id' => $empleado->id, 'periodo' => $periodoPago],
            [
                'company_id'                  => $companyId,
                'tipo'                        => $tipo,
                'anio'                        => $anio,
                'nombres'                     => $empleado->nombres,
                'apellidos'                   => $empleado->apellidos,
                'dni'                         => $empleado->dni,
                'cargo'                       => $empleado->cargo,
                'sueldo_base'                 => $empleado->sueldo_base,
                'asignacion_familiar'         => $asignacionFamiliar,
                'meses_computables'           => $mesesComputables,
                'meses_con_comisiones'        => $mesesConComisiones,
                'promedio_comisiones'         => $promedioComisiones,
                'meses_con_horas_extra'       => $mesesConHorasExtra,
                'promedio_horas_extra'        => $promedioHorasExtra,
                'remuneracion_computable'     => $remuneracionComputable,
                'monto_gratificacion'         => $montoGratificacion,
                'bonificacion_extraordinaria' => $bonificacionExtraordinaria,
                'monto_total'                 => $montoTotal,
                'calculado_por'               => Auth::id(),
                'calculado_at'                => now(),
            ]
        );
    }

    /**
     * CTS (Compensación por Tiempo de Servicios) — depósito de mayo (semestre
     * noviembre-abril) y noviembre (semestre mayo-octubre).
     *
     * Remuneración computable = sueldo base + asignación familiar + promedio
     * de comisiones/horas extra de los 6 meses del semestre CTS (misma regla
     * de "al menos 3 de 6" que gratificación) + 1/6 de la gratificación
     * percibida dentro de ese semestre (diciembre anterior para el depósito
     * de mayo, julio de este año para el de noviembre — regla legal).
     * Monto = remuneración computable ÷ 12 × meses computables del semestre.
     */
    public function calcularPeriodoCts(int $companyId, string $tipo, int $anio): Collection
    {
        if (!in_array($tipo, ['mayo', 'noviembre'], true)) {
            throw new \InvalidArgumentException("Tipo de CTS inválido: {$tipo}");
        }

        $empleados = Employee::where('company_id', $companyId)
            ->where('active', true)
            ->whereNotNull('sueldo_base')
            ->where('sueldo_base', '>', 0)
            ->get();

        $depositos = collect();

        DB::transaction(function () use ($empleados, $companyId, $tipo, $anio, &$depositos) {
            foreach ($empleados as $empleado) {
                $d = $this->calcularCtsEmpleado($empleado, $companyId, $tipo, $anio);
                if ($d) {
                    $depositos->push($d);
                }
            }
        });

        return $depositos;
    }

    public function calcularCtsEmpleado(
        Employee $empleado,
        int $companyId,
        string $tipo,
        int $anio,
    ): ?\App\Models\CtsDeposito {
        // Semestre CTS como pares [año, mes] — el de mayo cruza el año nuevo.
        if ($tipo === 'mayo') {
            $mesesSemestre = [
                [$anio - 1, 11], [$anio - 1, 12],
                [$anio, 1], [$anio, 2], [$anio, 3], [$anio, 4],
            ];
            $periodoPago = sprintf('%04d-05', $anio);
            $gratificacionRelevante = \App\Models\Gratificacion::where('employee_id', $empleado->id)
                ->where('tipo', 'diciembre')
                ->where('anio', $anio - 1)
                ->first();
        } else {
            $mesesSemestre = [
                [$anio, 5], [$anio, 6], [$anio, 7],
                [$anio, 8], [$anio, 9], [$anio, 10],
            ];
            $periodoPago = sprintf('%04d-11', $anio);
            $gratificacionRelevante = \App\Models\Gratificacion::where('employee_id', $empleado->id)
                ->where('tipo', 'julio')
                ->where('anio', $anio)
                ->first();
        }

        $fechaIngreso = $empleado->fecha_ingreso ? Carbon::parse($empleado->fecha_ingreso) : null;
        $fechaCese    = $empleado->fecha_cese ? Carbon::parse($empleado->fecha_cese) : null;

        $mesesComputables    = 0;
        $mesesConComisiones  = 0;
        $sumaComisiones      = 0.0;
        $mesesConHorasExtra  = 0;
        $sumaHorasExtra      = 0.0;

        foreach ($mesesSemestre as [$y, $m]) {
            $inicioMes = Carbon::create($y, $m, 1)->startOfMonth();
            $finMes    = Carbon::create($y, $m, 1)->endOfMonth();

            $activoEseMes = (!$fechaIngreso || $fechaIngreso->lte($finMes))
                && (!$fechaCese || $fechaCese->gte($inicioMes));

            if ($activoEseMes) {
                $mesesComputables++;
            }

            $periodoMes = sprintf('%04d-%02d', $y, $m);
            $liquidacion = PlanillaLiquidacion::where('employee_id', $empleado->id)
                ->where('periodo', $periodoMes)
                ->first();

            if (!$liquidacion) {
                continue;
            }

            if ((float) $liquidacion->comisiones > 0) {
                $mesesConComisiones++;
                $sumaComisiones += (float) $liquidacion->comisiones;
            }

            $horasExtraMes = (float) $liquidacion->importe_horas_extra_diurnas + (float) $liquidacion->importe_horas_extra_nocturnas;
            if ($horasExtraMes > 0) {
                $mesesConHorasExtra++;
                $sumaHorasExtra += $horasExtraMes;
            }
        }

        if ($mesesComputables === 0) {
            return null; // no trabajó ni un día del semestre CTS — no corresponde
        }

        $asignacionFamiliar = $empleado->aplica_asignacion_familiar
            ? round(self::RMV_2026 * 0.10, 2)
            : 0.0;

        $promedioComisiones = $mesesConComisiones >= 3 ? round($sumaComisiones / 6, 2) : 0.0;
        $promedioHorasExtra = $mesesConHorasExtra >= 3 ? round($sumaHorasExtra / 6, 2) : 0.0;

        $sextoGratificacion = $gratificacionRelevante
            ? round($gratificacionRelevante->monto_gratificacion / 6, 2)
            : 0.0;

        $remuneracionComputable = round(
            floatval($empleado->sueldo_base) + $asignacionFamiliar + $promedioComisiones + $promedioHorasExtra + $sextoGratificacion,
            2
        );

        $montoCts = round($remuneracionComputable / 12 * $mesesComputables, 2);

        return \App\Models\CtsDeposito::updateOrCreate(
            ['employee_id' => $empleado->id, 'periodo' => $periodoPago],
            [
                'company_id'              => $companyId,
                'tipo'                    => $tipo,
                'anio'                    => $anio,
                'nombres'                 => $empleado->nombres,
                'apellidos'               => $empleado->apellidos,
                'dni'                     => $empleado->dni,
                'cargo'                   => $empleado->cargo,
                'sueldo_base'             => $empleado->sueldo_base,
                'asignacion_familiar'     => $asignacionFamiliar,
                'meses_computables'       => $mesesComputables,
                'meses_con_comisiones'    => $mesesConComisiones,
                'promedio_comisiones'     => $promedioComisiones,
                'meses_con_horas_extra'   => $mesesConHorasExtra,
                'promedio_horas_extra'    => $promedioHorasExtra,
                'gratificacion_id'        => $gratificacionRelevante?->id,
                'sexto_gratificacion'     => $sextoGratificacion,
                'remuneracion_computable' => $remuneracionComputable,
                'monto_cts'               => $montoCts,
                'calculado_por'           => Auth::id(),
                'calculado_at'            => now(),
            ]
        );
    }

    /**
     * Liquidación por cese: vacaciones truncas + gratificación trunca +
     * CTS trunca + indemnización (solo si el motivo es despido arbitrario).
     *
     * NO incluye la remuneración pendiente del mes de cese — eso se calcula
     * con la "Liquidación de Planilla" normal de ese mes (usando los días
     * realmente trabajados hasta el cese), para no duplicar el cálculo.
     */
    public function calcularLiquidacionCese(int $employeeId, string $motivoCese, ?float $indemnizacionManual = null): \App\Models\LiquidacionCese
    {
        $empleado = Employee::findOrFail($employeeId);

        if (!$empleado->fecha_cese) {
            throw new \RuntimeException('El trabajador no tiene fecha de cese registrada en su ficha.');
        }

        $fechaCese    = Carbon::parse($empleado->fecha_cese);
        $fechaIngreso = $empleado->fecha_ingreso ? Carbon::parse($empleado->fecha_ingreso) : $fechaCese;
        $sueldo       = floatval($empleado->sueldo_base);
        $asignacionFamiliar = $empleado->aplica_asignacion_familiar
            ? round(self::RMV_2026 * 0.10, 2)
            : 0.0;

        // ── Vacaciones truncas ───────────────────────────────────────────────
        $saldoVacaciones = app(VacacionesService::class)->calcularSaldo($empleado, $fechaCese);
        $diasVacacionesTruncas = max(0, floatval($saldoVacaciones['saldo_actual'] ?? 0));
        $montoVacacionesTruncas = round(($sueldo + $asignacionFamiliar) / 30 * $diasVacacionesTruncas, 2);

        // ── Gratificación trunca (semestre en curso al momento del cese) ────
        $inicioSemestreGrat = $fechaCese->month <= 6
            ? Carbon::create($fechaCese->year, 1, 1)
            : Carbon::create($fechaCese->year, 7, 1);
        $finSemestreGrat = $fechaCese->month <= 6
            ? Carbon::create($fechaCese->year, 6, 30)
            : Carbon::create($fechaCese->year, 12, 31);

        [$mesesGratTrunca, $promComisionesGrat, $promHorasExtraGrat] = $this->prorrateoTrunca(
            $empleado, max($inicioSemestreGrat, $fechaIngreso), $fechaCese, $inicioSemestreGrat, $finSemestreGrat
        );

        $remuneracionComputableGrat = round($sueldo + $asignacionFamiliar + $promComisionesGrat + $promHorasExtraGrat, 2);
        $montoGratTrunca = round($remuneracionComputableGrat / 6 * $mesesGratTrunca, 2);
        $bonifTrunca     = round($montoGratTrunca * 0.09, 2);

        // ── CTS trunca (semestre CTS en curso al momento del cese) ──────────
        // Semestre CTS "mayo" = nov-abr, "noviembre" = may-oct.
        if (in_array($fechaCese->month, [11, 12, 1, 2, 3, 4], true)) {
            $inicioSemestreCts = $fechaCese->month >= 11
                ? Carbon::create($fechaCese->year, 11, 1)
                : Carbon::create($fechaCese->year - 1, 11, 1);
            $finSemestreCts = $inicioSemestreCts->copy()->addMonths(5)->endOfMonth();
        } else {
            $inicioSemestreCts = Carbon::create($fechaCese->year, 5, 1);
            $finSemestreCts    = Carbon::create($fechaCese->year, 10, 31);
        }

        [$mesesCtsTrunca, $promComisionesCts, $promHorasExtraCts] = $this->prorrateoTrunca(
            $empleado, max($inicioSemestreCts, $fechaIngreso), $fechaCese, $inicioSemestreCts, $finSemestreCts
        );

        // 1/6 de la gratificación trunca recién calculada (es la que cae
        // dentro de este semestre CTS, por construcción).
        $sextoGratificacion = round($montoGratTrunca / 6, 2);

        $remuneracionComputableCts = round($sueldo + $asignacionFamiliar + $promComisionesCts + $promHorasExtraCts + $sextoGratificacion, 2);
        $montoCtsTrunca = round($remuneracionComputableCts / 12 * $mesesCtsTrunca, 2);

        // ── Indemnización por despido arbitrario ────────────────────────────
        // 1.5 sueldos por año completo de servicio (dozavos por meses
        // adicionales), tope 12 sueldos — Art. 38 D.Leg. 728. Solo aplica si
        // el motivo es despido arbitrario; para los demás motivos es 0.
        // Se puede pasar un monto manual (ej. transacción/acuerdo distinto).
        $indemnizacion = 0.0;
        if ($indemnizacionManual !== null) {
            $indemnizacion = round($indemnizacionManual, 2);
        } elseif ($motivoCese === 'despido_arbitrario') {
            $mesesServicio = $fechaIngreso->diffInMonths($fechaCese);
            $indemnizacion = round(min(1.5 * $sueldo / 12 * $mesesServicio, 12 * $sueldo), 2);
        }

        $montoTotal = round(
            $montoVacacionesTruncas + $montoGratTrunca + $bonifTrunca + $montoCtsTrunca + $indemnizacion,
            2
        );

        return \App\Models\LiquidacionCese::updateOrCreate(
            ['employee_id' => $empleado->id, 'fecha_cese' => $fechaCese->toDateString()],
            [
                'company_id'   => $empleado->company_id,
                'motivo_cese'  => $motivoCese,
                'nombres'      => $empleado->nombres,
                'apellidos'    => $empleado->apellidos,
                'dni'          => $empleado->dni,
                'cargo'        => $empleado->cargo,
                'sueldo_base'  => $sueldo,
                'asignacion_familiar' => $asignacionFamiliar,
                'dias_vacaciones_truncas'  => $diasVacacionesTruncas,
                'monto_vacaciones_truncas' => $montoVacacionesTruncas,
                'meses_gratificacion_trunca' => $mesesGratTrunca,
                'monto_gratificacion_trunca' => $montoGratTrunca,
                'bonificacion_extraordinaria_trunca' => $bonifTrunca,
                'meses_cts_trunca' => $mesesCtsTrunca,
                'monto_cts_trunca' => $montoCtsTrunca,
                'indemnizacion' => $indemnizacion,
                'monto_total'   => $montoTotal,
                'calculado_por' => Auth::id(),
                'calculado_at'  => now(),
            ]
        );
    }

    /**
     * Helper compartido por gratificación trunca y CTS trunca: convierte
     * días transcurridos (desde $desde hasta $fechaCese) a "meses" de 30
     * días (tope 6), y calcula el promedio de comisiones/horas extra de
     * los meses del semestre [$inicioSemestre, $finSemestre] que tengan
     * liquidación mensual registrada — misma regla de "al menos 3 de 6"
     * que gratificación/CTS regulares.
     *
     * @return array{0: float, 1: float, 2: float} [mesesComputables, promedioComisiones, promedioHorasExtra]
     */
    private function prorrateoTrunca(Employee $empleado, Carbon $desde, Carbon $fechaCese, Carbon $inicioSemestre, Carbon $finSemestre): array
    {
        $dias = max(0, $desde->diffInDays($fechaCese) + 1);
        $mesesComputables = min(6.0, round($dias / 30, 2));

        $mesesConComisiones = 0;
        $sumaComisiones     = 0.0;
        $mesesConHorasExtra = 0;
        $sumaHorasExtra     = 0.0;

        $cursor = $inicioSemestre->copy();
        while ($cursor->lte($finSemestre)) {
            $periodoMes = $cursor->format('Y-m');
            $liquidacion = PlanillaLiquidacion::where('employee_id', $empleado->id)
                ->where('periodo', $periodoMes)
                ->first();

            if ($liquidacion) {
                if ((float) $liquidacion->comisiones > 0) {
                    $mesesConComisiones++;
                    $sumaComisiones += (float) $liquidacion->comisiones;
                }

                $horasExtraMes = (float) $liquidacion->importe_horas_extra_diurnas + (float) $liquidacion->importe_horas_extra_nocturnas;
                if ($horasExtraMes > 0) {
                    $mesesConHorasExtra++;
                    $sumaHorasExtra += $horasExtraMes;
                }
            }

            $cursor->addMonthNoOverflow();
        }

        $promedioComisiones = $mesesConComisiones >= 3 ? round($sumaComisiones / 6, 2) : 0.0;
        $promedioHorasExtra = $mesesConHorasExtra >= 3 ? round($sumaHorasExtra / 6, 2) : 0.0;

        return [$mesesComputables, $promedioComisiones, $promedioHorasExtra];
    }

    /**
     * Participación en las utilidades — reparto legal (D. Leg. 892):
     *   - 50% del pool proporcional a los DÍAS trabajados en el año por
     *     cada trabajador, sobre el total de días trabajados por todos.
     *   - 50% del pool proporcional a la REMUNERACIÓN percibida en el año
     *     por cada trabajador, sobre el total de remuneraciones de todos.
     *   - Tope: lo que le toca a cada trabajador no puede exceder 18
     *     remuneraciones mensuales de ese trabajador — el exceso NO se
     *     redistribuye entre los demás (por ley va a un fondo estatal,
     *     fuera del alcance de este sistema).
     *
     * El monto total del pool a repartir ($montoTotalARepartir) es un dato
     * que entrega contabilidad (resulta de aplicar el % legal según la
     * actividad de la empresa sobre la renta neta anual) — este sistema no
     * calcula la renta neta ni el % por actividad, solo hace el reparto
     * entre trabajadores una vez que ese monto ya está definido.
     */
    public function calcularPeriodoUtilidades(int $companyId, int $anioEjercicio, float $montoTotalARepartir, string $periodoPago): Collection
    {
        $empleados = Employee::where('company_id', $companyId)
            ->whereNotNull('sueldo_base')
            ->where('sueldo_base', '>', 0)
            ->get();

        // Insumos por trabajador: días y remuneración bruta del año fiscal,
        // sumados desde las liquidaciones mensuales ya calculadas.
        $insumos = [];
        $totalDias = 0;
        $totalRemuneracion = 0.0;

        foreach ($empleados as $empleado) {
            $liquidaciones = PlanillaLiquidacion::where('employee_id', $empleado->id)
                ->where('periodo', 'like', $anioEjercicio . '-%')
                ->get();

            $dias = (int) $liquidaciones->sum('dias_trabajados');
            $remuneracion = (float) $liquidaciones->sum('remuneracion_bruta');

            if ($dias <= 0 && $remuneracion <= 0) {
                continue; // no trabajó ese año, no participa
            }

            $insumos[$empleado->id] = ['empleado' => $empleado, 'dias' => $dias, 'remuneracion' => $remuneracion];
            $totalDias += $dias;
            $totalRemuneracion += $remuneracion;
        }

        if (empty($insumos) || $totalDias <= 0 || $totalRemuneracion <= 0) {
            throw new \RuntimeException(
                "No hay liquidaciones mensuales calculadas para el año {$anioEjercicio} — calcula primero la Liquidación de Planilla de esos meses."
            );
        }

        $poolPorDias = $montoTotalARepartir * 0.5;
        $poolPorRemuneracion = $montoTotalARepartir * 0.5;

        $utilidades = collect();

        DB::transaction(function () use ($insumos, $totalDias, $totalRemuneracion, $poolPorDias, $poolPorRemuneracion, $companyId, $anioEjercicio, $periodoPago, &$utilidades) {
            foreach ($insumos as $datos) {
                $empleado = $datos['empleado'];

                $montoPorDias = round($poolPorDias * ($datos['dias'] / $totalDias), 2);
                $montoPorRemuneracion = round($poolPorRemuneracion * ($datos['remuneracion'] / $totalRemuneracion), 2);
                $montoBruto = round($montoPorDias + $montoPorRemuneracion, 2);

                $tope = round(18 * floatval($empleado->sueldo_base), 2);
                $topeAplicado = $montoBruto > $tope;
                $montoPagado = $topeAplicado ? $tope : $montoBruto;

                $utilidades->push(\App\Models\Utilidad::updateOrCreate(
                    ['employee_id' => $empleado->id, 'anio_ejercicio' => $anioEjercicio],
                    [
                        'company_id'    => $companyId,
                        'periodo'       => $periodoPago,
                        'nombres'       => $empleado->nombres,
                        'apellidos'     => $empleado->apellidos,
                        'dni'           => $empleado->dni,
                        'cargo'         => $empleado->cargo,
                        'sueldo_base'   => $empleado->sueldo_base,
                        'dias_trabajados_anual' => $datos['dias'],
                        'remuneracion_anual'    => $datos['remuneracion'],
                        'monto_por_dias'         => $montoPorDias,
                        'monto_por_remuneracion' => $montoPorRemuneracion,
                        'monto_bruto'            => $montoBruto,
                        'tope_18_remuneraciones' => $tope,
                        'tope_aplicado'          => $topeAplicado,
                        'monto_pagado'           => $montoPagado,
                        'calculado_por' => Auth::id(),
                        'calculado_at'  => now(),
                    ]
                ));
            }
        });

        return $utilidades;
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
