<?php

namespace App\Services;

use App\Models\AfpTasa;
use App\Models\AttendanceRecord;
use App\Models\Employee;
use App\Models\ParametroLegal;
use App\Models\PlanillaLiquidacion;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class PlanillaService
{
    // ── Recargos horas extra (Ley 25593 Perú) ────────────────────────────────
    const RECARGO_HE_DIURNA   = 0.25; // primeras 2h del día: +25%
    const RECARGO_HE_NOCTURNA = 0.35; // horas adicionales:   +35%

    // ── Aportes de empleador ──────────────────────────────────────────────
    const TASA_ESSALUD       = 0.09;   // 9% sobre remuneración bruta
    const TASA_SEGURO_VIDA_EMPLEADO = 0.0053; // 0.53% empleados (D.Leg 688). Obreros: 0.71%/1.46% — no soportado aún.

    // ── EPS (Sanitas Perú) ────────────────────────────────────────────────
    const IGV                   = 0.18;
    const CREDITO_EPS_PORCENTAJE = 0.25;
    const APORTE_EMPRESA_EPS     = 0.30; // el trabajador asume el 70% restante

    /**
     * RMV y tasa ONP ya NO son constantes fijas en código — se leen de la
     * tabla parametros_legales (pantalla "Parámetros Legales" en
     * Administración), con vigencia por fecha igual que afp_tasas. Así,
     * cuando el gobierno sube la RMV, se actualiza desde el sistema sin
     * tocar código ni redesplegar, y los periodos pasados se siguen
     * recalculando con el valor que tenían en su momento (oct. 2026).
     */
    private function rmv($fecha = null): float
    {
        return ParametroLegal::valor(ParametroLegal::RMV, $fecha);
    }

    private function tasaOnp($fecha = null): float
    {
        return ParametroLegal::valor(ParametroLegal::TASA_ONP, $fecha);
    }

    public function calcularPeriodo(int $companyId, string $periodo, array $bonosEspeciales = [], array $otrosDescuentos = [], array $adelantos = [], array $subsidiosEnfermedad = [], array $subsidiosMaternidad = [], array $retencionesManuales5ta = []): Collection
    {
        [$year, $month] = explode('-', $periodo);

        $empleados = Employee::where('company_id', $companyId)
            ->where('active', true)
            ->whereNotNull('sueldo_base')
            ->where('sueldo_base', '>', 0)
            ->get();

        $liquidaciones = collect();

        DB::transaction(function () use ($empleados, $companyId, $periodo, $year, $month, $bonosEspeciales, $otrosDescuentos, $adelantos, $subsidiosEnfermedad, $subsidiosMaternidad, $retencionesManuales5ta, &$liquidaciones) {
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
                    array_key_exists($empleado->id, $retencionesManuales5ta) ? (float) $retencionesManuales5ta[$empleado->id] : null,
                );
                $liquidaciones->push($liquidacion);
            }

            // ── Segunda pasada: crédito EPS como fondo compartido ───────────
            // El crédito (25%) se calcula sobre el EsSalud TOTAL de todos los
            // afiliados a EPS de la empresa este periodo, y se reparte en
            // partes iguales entre ellos (no 25% del EsSalud de cada uno por
            // separado) — confirmado con RRHH (Cielo, oct. 2026). Se necesita
            // el essalud_empleador ya calculado en la primera pasada para
            // armar el fondo, por eso va después.
            $afiliadosEps = $empleados->filter(fn ($e) => floatval($e->monto_eps_mensual_con_igv) > 0);

            if ($afiliadosEps->isNotEmpty()) {
                $sumaEssaludAfiliados = (float) PlanillaLiquidacion::where('company_id', $companyId)
                    ->where('periodo', $periodo)
                    ->whereIn('employee_id', $afiliadosEps->pluck('id'))
                    ->sum('essalud_empleador');

                $creditoPorAfiliado = round(
                    $sumaEssaludAfiliados * self::CREDITO_EPS_PORCENTAJE / $afiliadosEps->count(),
                    2
                );

                foreach ($afiliadosEps as $empleado) {
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
                        array_key_exists($empleado->id, $retencionesManuales5ta) ? (float) $retencionesManuales5ta[$empleado->id] : null,
                        $creditoPorAfiliado,
                    );

                    $liquidaciones = $liquidaciones->map(
                        fn ($l) => $l->employee_id === $empleado->id ? $liquidacion : $l
                    );
                }
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
        ?float $retencion5taManual = null,
        ?float $epsCreditoPool = null,
    ): PlanillaLiquidacion {

        // Si no se pasó un valor nuevo (default 0), preservar lo que ya
        // estaba guardado — evita perder bonos/descuentos manuales cada vez
        // que se recalcula la planilla (ej. al editar un registro de
        // asistencia y volver a calcular). Para poner explícitamente en 0,
        // hay que borrar la liquidación o escribir 0 a mano en el Repeater.
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
            // A diferencia de los anteriores, 0 es un valor válido de
            // override manual (retención S/0 a propósito) — se preserva lo
            // guardado solo cuando esta vez NO se pasó nada (null).
            if ($retencion5taManual === null && $existente->retencion_5ta_manual !== null) {
                $retencion5taManual = (float) $existente->retencion_5ta_manual;
            }
        }


        $mesNombre     = $this->periodoANombre($periodo);
        $sueldo        = floatval($empleado->sueldo_base);
        $fechaRef      = Carbon::create($year, $month, 1);
        $rmv           = $this->rmv($fechaRef);

        // Rango de asistencia (tardanzas, faltas, horas extra, días
        // trabajados): del 26 del mes anterior al 25 de este mes, NO el mes
        // calendario — así es como RRHH corta para calcular planilla en
        // todas las empresas (confirmado con Cielo, oct. 2026), igual que ya
        // se hacía para comisiones. El "Periodo" que se muestra en la
        // boleta/PLAME sigue siendo el mes calendario (ej. "09/2026") para
        // efectos legales ante SUNAT — esto solo cambia qué días se cuentan.
        $finPeriodo    = Carbon::create($year, $month, 25)->endOfDay();
        $inicioPeriodo = $finPeriodo->copy()->subMonthNoOverflow()->addDay()->startOfDay();

        $diasLaborables = $this->contarDiasLaborables($inicioPeriodo, $finPeriodo);

        $asistencias = AttendanceRecord::where('employee_id', $empleado->id)
            ->whereBetween('fecha', [$inicioPeriodo->toDateString(), $finPeriodo->toDateString()])
            ->get();

        $diasTrabajados      = $asistencias->whereIn('estado', ['presente', 'tarde'])->count();
        $diasVacaciones      = $asistencias->where('estado', 'vacaciones')->count();
        $diasJustificados    = $asistencias->where('justificado', true)->count();
        $horasExtraDiurnas   = floatval($asistencias->sum('horas_extra_diurnas'));
        $horasExtraNocturnas = floatval($asistencias->sum('horas_extra_nocturnas'));

        // Trabajadores exonerados de registro (dirección / sin fiscalización
        // inmediata, Art. 6 D.S. 004-2006-TR) nunca deben tener descuento por
        // tardanzas ni faltas, aunque el biométrico haya marcado algo — RRHH,
        // set. 2026 (caso Vizcarra Zapata, Quantum).
        $diasFalta         = $empleado->exonerado_registro ? 0 : $asistencias->where('estado', 'ausente')->where('justificado', false)->count();
        $totalMinutosTarde = $empleado->exonerado_registro ? 0 : $asistencias->sum('minutos_tarde');

        $valorDia    = $sueldo / 30;
        $valorHora   = $sueldo / 30 / 8;
        $valorMinuto = $sueldo / 30 / 8 / 60;

        // El sueldo de los días de vacaciones se saca de aquí (0121) y se
        // mueve a 0118, para no duplicar — confirmado con RRHH.
        $sueldoProporcional = $sueldo - ($valorDia * $diasFalta) - ($valorDia * $diasVacaciones);

        // Asignación familiar: monto fijo = 10% de la RMV, solo si el
        // empleado tiene el switch activado (hijos menores de 18, o hasta
        // 24 si estudian). Se recalcula sola si cambia la RMV en
        // Administración > Parámetros Legales.
        $asignacionFamiliar = $empleado->aplica_asignacion_familiar
            ? round($rmv * 0.10, 2)
            : 0.0;

        // Remuneración vacacional (0118): (sueldo + asignación familiar) ÷ 30
        // × días de vacaciones — la asignación forma parte de la remuneración
        // vacacional (corregido con RRHH, oct. 2026; antes solo el sueldo) —
        // + promedio de COMISIONES de los últimos 6 meses ANTES del mes
        // actual, prorrateado por los días de vacaciones tomados.
        // Confirmado con RRHH: "x" = solo comisiones, no otras variables.
        // El 0121 sigue descontando solo el sueldo de esos días y la
        // asignación (0201) sigue pagándose completa en el mes.
        $sueldoVacacional     = round((($sueldo + $asignacionFamiliar) / 30) * $diasVacaciones, 2);
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
        // Dos esquemas posibles (configurables por trabajador):
        //   - 'cartera_propia' (default): filtra por employee_id real, cada
        //     vendedor recibe solo lo suyo (ya calculado por fila en
        //     ComisionesService con su propio %).
        //   - 'total_empresa': el % (fijo o por escala) se aplica sobre la
        //     suma de TODAS las facturas cobradas del periodo, sin importar
        //     el vendedor (caso Ernesto/Jorge — confirmado con RRHH set.2026).
        $comisiones = 0.0;
        if ($empleado->aplica_comision) {
            $uploadIds = \App\Models\ComisionUpload::where('periodo', $periodo)
                ->where('company_id', $companyId)
                ->pluck('id');

            if ($uploadIds->isNotEmpty()) {
                if ($empleado->tipo_base_comision === 'total_empresa') {
                    $baseTotalEmpresa = floatval(
                        \App\Models\ComisionDetalle::whereIn('comision_upload_id', $uploadIds)
                            ->whereIn('estado', ['cobrada', 'huerfana'])
                            ->sum('base_comision_cobrada')
                    );

                    $porcentaje = $empleado->porcentajeComisionAplicable($baseTotalEmpresa);
                    $comisiones = round($baseTotalEmpresa * $porcentaje, 2);
                } else {
                    $comisiones = floatval(
                        \App\Models\ComisionDetalle::whereIn('comision_upload_id', $uploadIds)
                            ->where('employee_id', $empleado->id)
                            ->where('estado', 'cobrada')
                            ->sum('comision_calculada')
                    );
                }
            }
        }

        $descuentoTardanzas = round($valorMinuto * $totalMinutosTarde, 2);
        $descuentoFaltas    = round($valorDia    * $diasFalta, 2);

        // (La asignación familiar se calcula más arriba, antes de las
        // vacaciones, porque forma parte de su base de pago.)

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
        $tasaPension          = $this->tasaOnp($fechaRef);
        $descuentoPension      = 0.0;

        if ($esAfp) {
            $tasaAfp = AfpTasa::vigentePara($empleado->sistema_pensiones, Carbon::create($year, $month, 25)->toDateString());

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
            $descuentoPension = round($bruto * $tasaPension, 2);
        }

        $descuento5ta = 0.0;
        if ($empleado->aplica_5ta_categoria) {
            if ($retencion5taManual !== null) {
                // Override manual (RRHH, set. 2026): mientras el sistema no
                // tenga histórico de ingresos ene-ago 2026 para proyectar
                // correctamente, RRHH calcula la retención aparte y la
                // ingresa aquí. Desde enero 2027 debería dejarse en
                // automático (no pasar este valor).
                $descuento5ta = round($retencion5taManual, 2);
            } else {
                $calculadora5ta = app(\App\Services\Renta5taCalculator::class);

                $detalle5ta = $empleado->aplica_comision
                    ? $calculadora5ta->calcular($empleado, $comisiones, $year, $month)
                    : $calculadora5ta->calcularNoComisionado($empleado, $sueldo + $asignacionFamiliar, $year, $month);

                $descuento5ta = $detalle5ta['cuota_mensual'];
            }
        }

        // ── EsSalud (empleador) — se calcula ANTES del neto porque el crédito
        // EPS depende de este monto. Base mínima es la RMV.
        $baseEssalud      = max($bruto, $rmv);
        $essaludEmpleador = round($baseEssalud * self::TASA_ESSALUD, 2);

        // ── EPS: solo si el trabajador tiene plan asignado (monto > 0).
        // Fórmula corregida con RRHH (Cielo, oct. 2026): el crédito EPS NO es
        // 25% del EsSalud de CADA trabajador por separado — es un fondo único
        // por empresa (25% del EsSalud mensual de TODOS los afiliados a EPS)
        // que se reparte EN PARTES IGUALES entre esos afiliados, sin importar
        // cuánto gane cada uno. $epsCreditoPool ya viene calculado así desde
        // calcularPeriodo(); si no se pasa (ej. prueba aislada), se usa el
        // 25% individual como aproximación de respaldo.
        //   1) quitar IGV del costo del plan
        //   2) restar el crédito EPS (del fondo compartido)
        //   3) repartir el resto 30% empresa / 70% trabajador
        $epsCredito             = 0.0;
        $epsAporteEmpresa       = 0.0;
        $epsDescuentoTrabajador = 0.0;

        if (floatval($empleado->monto_eps_mensual_con_igv) > 0) {
            $importeEpsSinIgv = round(floatval($empleado->monto_eps_mensual_con_igv) / (1 + self::IGV), 2);
            $epsCredito       = $epsCreditoPool !== null
                ? round($epsCreditoPool, 2)
                : round($essaludEmpleador * self::CREDITO_EPS_PORCENTAJE, 2);
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
                'retencion_5ta_manual'          => $retencion5taManual,
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
        $fechaRef  = Carbon::create($year, $month, 1);

        $asignacionFamiliar = $empleado->aplica_asignacion_familiar
            ? round($this->rmv($fechaRef) * 0.10, 2)
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
        $tasaPension          = $this->tasaOnp($fechaRef);
        $descuentoPension     = 0.0;

        if ($esAfp) {
            $tasaAfp = AfpTasa::vigentePara($empleado->sistema_pensiones, Carbon::create($year, $month, 25)->toDateString());

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
            $descuentoPension = round($baseQuincenal * $tasaPension / 2, 2);
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

            // Montos pagados fuera del sistema (cts_insumos_historicos): se usan
            // solo si la liquidación del mes no trae ese concepto.
            $insumos = \App\Models\CtsInsumoHistorico::where('employee_id', $empleado->id)
                ->where('periodo', $periodoMes)
                ->pluck('monto', 'concepto');

            $comisionesMes = (float) ($liquidacion?->comisiones ?? 0);
            if ($comisionesMes <= 0) {
                $comisionesMes = (float) ($insumos['comisiones'] ?? 0);
            }
            if ($comisionesMes > 0) {
                $mesesConComisiones++;
                $sumaComisiones += $comisionesMes;
            }

            $horasExtraMes = (float) ($liquidacion?->importe_horas_extra_diurnas ?? 0)
                + (float) ($liquidacion?->importe_horas_extra_nocturnas ?? 0);
            if ($horasExtraMes <= 0) {
                $horasExtraMes = (float) ($insumos['horas_extra'] ?? 0);
            }
            if ($horasExtraMes > 0) {
                $mesesConHorasExtra++;
                $sumaHorasExtra += $horasExtraMes;
            }
        }

        if ($mesesComputables === 0) {
            return null; // no trabajó ni un día del semestre — no le corresponde
        }

        $asignacionFamiliar = $empleado->aplica_asignacion_familiar
            ? round($this->rmv($periodoPago . '-01') * 0.10, 2)
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

            // Depósitos de un cálculo anterior de trabajadores que ya no corresponden
            // (inactivos, cesados o sin sueldo): se quitan para que no sigan saliendo.
            \App\Models\CtsDeposito::where('company_id', $companyId)
                ->where('tipo', $tipo)
                ->where('anio', $anio)
                ->whereNotIn('employee_id', $depositos->pluck('employee_id'))
                ->delete();
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
        $mesesConBonos       = 0;
        $sumaBonos           = 0.0;

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

            // Montos pagados fuera del sistema (cts_insumos_historicos): se usan
            // solo si la liquidación del mes no trae ese concepto.
            $insumos = \App\Models\CtsInsumoHistorico::where('employee_id', $empleado->id)
                ->where('periodo', $periodoMes)
                ->pluck('monto', 'concepto');

            $comisionesMes = (float) ($liquidacion?->comisiones ?? 0);
            if ($comisionesMes <= 0) {
                $comisionesMes = (float) ($insumos['comisiones'] ?? 0);
            }
            if ($comisionesMes > 0) {
                $mesesConComisiones++;
                $sumaComisiones += $comisionesMes;
            }

            $horasExtraMes = (float) ($liquidacion?->importe_horas_extra_diurnas ?? 0)
                + (float) ($liquidacion?->importe_horas_extra_nocturnas ?? 0);
            if ($horasExtraMes <= 0) {
                $horasExtraMes = (float) ($insumos['horas_extra'] ?? 0);
            }
            if ($horasExtraMes > 0) {
                $mesesConHorasExtra++;
                $sumaHorasExtra += $horasExtraMes;
            }

            // Bono regular (encargatura / bono fijo mensual): computable.
            $bonoMes = (float) ($liquidacion?->bono_encargatura ?? 0);
            if ($bonoMes <= 0) {
                $bonoMes = (float) ($insumos['bono'] ?? 0);
            }
            if ($bonoMes > 0) {
                $mesesConBonos++;
                $sumaBonos += $bonoMes;
            }
        }

        if ($mesesComputables === 0) {
            return null; // no trabajó ni un día del semestre CTS — no corresponde
        }

        $asignacionFamiliar = $empleado->aplica_asignacion_familiar
            ? round($this->rmv($periodoPago . '-01') * 0.10, 2)
            : 0.0;

        $promedioComisiones = $mesesConComisiones >= 3 ? round($sumaComisiones / 6, 2) : 0.0;
        $promedioHorasExtra = $mesesConHorasExtra >= 3 ? round($sumaHorasExtra / 6, 2) : 0.0;
        $promedioBonos      = $mesesConBonos >= 3 ? round($sumaBonos / 6, 2) : 0.0;

        $sextoGratificacion = $gratificacionRelevante
            ? round($gratificacionRelevante->monto_gratificacion / 6, 2)
            : 0.0;

        $remuneracionComputable = round(
            floatval($empleado->sueldo_base) + $asignacionFamiliar + $promedioComisiones + $promedioHorasExtra + $promedioBonos + $sextoGratificacion,
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
                'meses_con_bonos'         => $mesesConBonos,
                'promedio_bonos'          => $promedioBonos,
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
    public function calcularLiquidacionCese(
        int $employeeId,
        string $motivoCese,
        ?float $indemnizacionManual = null,
        ?float $promedioComisionesGratManual = null,
        ?float $promedioComisionesCtsManual = null,
        ?float $promedioComisionesVacacionesManual = null,
    ): \App\Models\LiquidacionCese {
        $empleado = Employee::findOrFail($employeeId);

        if (!$empleado->fecha_cese) {
            throw new \RuntimeException('El trabajador no tiene fecha de cese registrada en su ficha.');
        }

        $fechaCese    = Carbon::parse($empleado->fecha_cese);
        $fechaIngreso = $empleado->fecha_ingreso ? Carbon::parse($empleado->fecha_ingreso) : $fechaCese;
        $sueldo       = floatval($empleado->sueldo_base);
        $asignacionFamiliar = $empleado->aplica_asignacion_familiar
            ? round($this->rmv($fechaCese) * 0.10, 2)
            : 0.0;

        // ── Vacaciones: truncas vs. remuneración vacacional + indemnización ──
        // Regla LEGAL correcta (corregida set. 2026 tras revisión con RRHH —
        // "vacaciones no gozadas" en el lenguaje de RRHH ES la misma cosa
        // que "remuneración vacacional pendiente" aquí, solo con otro
        // nombre; antes este código las trataba como si solo aplicaran
        // juntas a los 24 meses, lo cual era el bug):
        //  - "Vacaciones truncas" = el saldo del periodo EN CURSO, todavía
        //    sin completar los 12 meses para ganarlo.
        //  - Apenas hay un periodo COMPLETO ya ganado (30 días = 12 meses)
        //    y no gozado, corresponde pagar la "remuneración vacacional"
        //    de ese periodo completo (1 remuneración) — SIN esperar ningún
        //    plazo adicional. Esos 30 días salen del saldo de truncas para
        //    no contarlos dos veces.
        //  - SOLO SI, ADEMÁS, pasó más de un año desde que se pudo gozar sin
        //    hacerlo (24 meses en total desde que se empezó a generar ese
        //    periodo), se suma la "indemnización vacacional" (1 remuneración
        //    MÁS, como penalidad) — esta sí depende del plazo vencido.
        // El saldo (VacacionesService) no distingue de qué periodo viene
        // cada día acumulado, así que se aproxima con la antigüedad desde
        // fecha_ultima_vacacion (o fecha_ingreso si nunca tomó).
        $saldoVacaciones = app(VacacionesService::class)->calcularSaldo($empleado, $fechaCese);
        $diasSaldoTotal = max(0, floatval($saldoVacaciones['saldo_actual'] ?? 0));

        $fechaCorteVacaciones = $empleado->fecha_ultima_vacacion
            ? Carbon::parse($empleado->fecha_ultima_vacacion)
            : $fechaIngreso;
        $mesesSinGozar = $fechaCorteVacaciones->diffInMonths($fechaCese);

        // Promedio de comisiones de los últimos 6 meses (regla RRHH set.
        // 2026, "≥3 de 6" igual que gratificación/CTS): para comisionistas,
        // TODA la remuneración vacacional (pendiente, truncas e
        // indemnización) debe incluir el promedio de comisiones, no solo el
        // sueldo. Si no hay comisiones cargadas al sistema (empresa nueva,
        // historial no migrado), se puede ingresar a mano.
        $promComisionesVacaciones = $promedioComisionesVacacionesManual !== null
            ? round($promedioComisionesVacacionesManual, 2)
            : $this->promedioComisionesUltimosMeses($empleado, $fechaCese);

        $baseVacacional = round($sueldo + $asignacionFamiliar + $promComisionesVacaciones, 2);

        $remuneracionVacacionalPendiente = 0.0;
        $indemnizacionVacacional = 0.0;
        $diasVacacionesTruncas = $diasSaldoTotal;

        if ($diasSaldoTotal >= 30) {
            $remuneracionVacacionalPendiente = $baseVacacional;
            $diasVacacionesTruncas = $diasSaldoTotal - 30;

            if ($mesesSinGozar >= 24) {
                $indemnizacionVacacional = $baseVacacional;
            }
        }

        // El monto de "truncas" (periodo en curso, sin completar) se calcula
        // en MESES CALENDARIO desde el último aniversario cumplido — no en
        // días÷30 sobre el saldo total — para que calce con la metodología
        // de RRHH (base÷12×meses) y no sobrecuente por meses de 31 días,
        // mismo criterio ya aplicado a gratificación/CTS trunca. El inicio
        // del periodo en curso es el aniversario más reciente (fecha de
        // corte + tantos años completos como periodos ya ganados).
        $inicioPeriodoEnCurso = $fechaCorteVacaciones->copy()->addYears((int) floor($mesesSinGozar / 12));
        $finInclusiveVac      = $fechaCese->copy()->addDay();
        $mesesCompletosVac    = max(0, (int) floor($inicioPeriodoEnCurso->diffInMonths($finInclusiveVac)));
        $fechaTrasCompletosVac = $inicioPeriodoEnCurso->copy()->addMonths($mesesCompletosVac);
        $diasRestantesVac      = max(0, $fechaTrasCompletosVac->diffInDays($finInclusiveVac));
        $mesesTruncasVacaciones = min(12.0, round($mesesCompletosVac + $diasRestantesVac / 30, 2));

        $montoVacacionesTruncas = round($baseVacacional / 12 * $mesesTruncasVacaciones, 2);
        // Se guarda en días (meses×30) solo para que la columna siga siendo
        // legible/consistente con el monto — el cálculo real usa meses.
        $diasVacacionesTruncas = round($mesesTruncasVacaciones * 30, 2);

        // ── AFP/ONP y EsSalud sobre vacaciones (pedido RRHH set. 2026) ──────
        // La indemnización vacacional NO lleva descuento (es una
        // indemnización, no remuneración) — solo truncas + remuneración
        // vacacional pendiente, que sí son remuneración pensionable.
        $baseAfpVacaciones = round($montoVacacionesTruncas + $remuneracionVacacionalPendiente, 2);
        $descuentoAfpVacaciones = 0.0;

        if (str_starts_with((string) $empleado->sistema_pensiones, 'afp_')) {
            $tasaAfpVac = AfpTasa::vigentePara($empleado->sistema_pensiones);
            if ($tasaAfpVac) {
                $tasaTotalAfpVac = floatval($tasaAfpVac->aporte_obligatorio)
                    + ($empleado->aplica_comision_flujo_afp ? floatval($tasaAfpVac->comision_flujo) : 0.0)
                    + floatval($tasaAfpVac->prima_seguro);
                $descuentoAfpVacaciones = round($baseAfpVacaciones * $tasaTotalAfpVac, 2);
            }
        } else {
            $descuentoAfpVacaciones = round($baseAfpVacaciones * $this->tasaOnp($fechaCese), 2);
        }

        $aporteEssaludVacaciones = round($baseAfpVacaciones * self::TASA_ESSALUD, 2);
        $totalVacacionesPorPagar = round(
            $montoVacacionesTruncas + $remuneracionVacacionalPendiente + $indemnizacionVacacional - $descuentoAfpVacaciones,
            2
        );

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

        // Override manual (RRHH set. 2026): mientras la empresa no cargue al
        // sistema las comisiones históricas (ComisionUpload) de meses
        // anteriores, el promedio automático sale en 0 aunque el trabajador
        // sí haya ganado comisiones reales. RRHH puede ingresar el promedio
        // real a mano en el formulario de "Calcular liquidación por cese".
        if ($promedioComisionesGratManual !== null) {
            $promComisionesGrat = round($promedioComisionesGratManual, 2);
        }

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

        if ($promedioComisionesCtsManual !== null) {
            $promComisionesCts = round($promedioComisionesCtsManual, 2);
        }

        // 1/6 de gratificación para CTS: la que corresponde al semestre de
        // gratificación que CAE DENTRO de este semestre CTS — julio para
        // CTS mayo-octubre, diciembre para CTS noviembre-abril. OJO: NO es
        // la gratificación trunca que se está calculando arriba para el
        // cese (esa es de un semestre de gratificación distinto, todavía
        // en curso). Bug real detectado por RRHH set. 2026 comparando
        // contra un cálculo manual: se estaba usando la trunca del
        // semestre equivocado.
        $sextoGratificacion = $this->resolverSextoGratificacionParaCts($empleado, $inicioSemestreCts);

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

        // El total usa vacaciones NETO (ya con el descuento AFP/ONP de
        // vacaciones aplicado) — el resto de conceptos (gratificación, CTS)
        // ya se calculan/guardan como corresponde a cada uno y no llevan
        // este descuento acá.
        $montoTotal = round(
            $totalVacacionesPorPagar
            + $montoGratTrunca + $bonifTrunca + $montoCtsTrunca + $indemnizacion,
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
                'promedio_comisiones_vacaciones_manual' => $promedioComisionesVacacionesManual,
                'remuneracion_vacacional_pendiente' => $remuneracionVacacionalPendiente,
                'indemnizacion_vacacional' => $indemnizacionVacacional,
                'descuento_afp_vacaciones' => $descuentoAfpVacaciones,
                'aporte_essalud_vacaciones' => $aporteEssaludVacaciones,
                'total_vacaciones_por_pagar' => $totalVacacionesPorPagar,
                'meses_gratificacion_trunca' => $mesesGratTrunca,
                'monto_gratificacion_trunca' => $montoGratTrunca,
                'bonificacion_extraordinaria_trunca' => $bonifTrunca,
                'promedio_comisiones_gratificacion_manual' => $promedioComisionesGratManual,
                'meses_cts_trunca' => $mesesCtsTrunca,
                'monto_cts_trunca' => $montoCtsTrunca,
                'promedio_comisiones_cts_manual' => $promedioComisionesCtsManual,
                'indemnizacion' => $indemnizacion,
                'monto_total'   => $montoTotal,
                'calculado_por' => Auth::id(),
                'calculado_at'  => now(),
            ]
        );
    }

    /**
     * Promedio de comisiones de los últimos 6 meses calendario ANTES del
     * cese (sin incluir el mes del cese, que aún no cierra) — regla "≥3 de
     * 6" para no promediar si hubo comisiones en menos de 3 de esos meses.
     * Usado para el valor diario de vacaciones truncas de comisionistas.
     */
    private function promedioComisionesUltimosMeses(Employee $empleado, Carbon $fechaCese): float
    {
        $mesesConComisiones = 0;
        $sumaComisiones     = 0.0;

        for ($i = 1; $i <= 6; $i++) {
            $periodo = $fechaCese->copy()->subMonthsNoOverflow($i)->format('Y-m');

            $liquidacion = PlanillaLiquidacion::where('employee_id', $empleado->id)
                ->where('periodo', $periodo)
                ->first();

            if ($liquidacion && (float) $liquidacion->comisiones > 0) {
                $mesesConComisiones++;
                $sumaComisiones += (float) $liquidacion->comisiones;
            }
        }

        return $mesesConComisiones >= 3 ? round($sumaComisiones / 6, 2) : 0.0;
    }

    /**
     * Resuelve 1/6 de la gratificación que corresponde incluir en la
     * remuneración computable de CTS: la del semestre de gratificación que
     * cae dentro del semestre CTS dado (julio si el CTS es mayo-octubre,
     * diciembre si es noviembre-abril) — NO la gratificación trunca del
     * cese, que es de un semestre distinto.
     *
     * Si esa gratificación ya está calculada/guardada (Gratificacion),
     * usa ese monto real. Si no (la empresa recién empezó a usar el
     * sistema y ese periodo nunca se procesó), la calcula ahora con la
     * misma lógica que "Calcular Gratificación" — si el trabajador estuvo
     * activo todo ese semestre, sale completa; si entró a mitad de ese
     * semestre, sale prorrateada igual que allá. Esto también la deja
     * guardada en Gratificacion, como si se hubiera calculado en su
     * momento.
     */
    private function resolverSextoGratificacionParaCts(Employee $empleado, Carbon $inicioSemestreCts): float
    {
        [$tipo, $anio] = $inicioSemestreCts->month === 5
            ? ['julio', $inicioSemestreCts->year]
            : ['diciembre', $inicioSemestreCts->year];

        $periodo = $tipo === 'julio' ? sprintf('%04d-07', $anio) : sprintf('%04d-12', $anio);

        $gratificacion = \App\Models\Gratificacion::where('employee_id', $empleado->id)
            ->where('periodo', $periodo)
            ->first();

        if (! $gratificacion) {
            $gratificacion = $this->calcularGratificacionEmpleado($empleado, $empleado->company_id, $tipo, $anio);
        }

        return $gratificacion ? round($gratificacion->monto_gratificacion / 6, 2) : 0.0;
    }

    /**
     * Helper compartido por gratificación trunca y CTS trunca: convierte el
     * tiempo transcurrido (desde $desde hasta $fechaCese, ambos incluidos)
     * a "meses computables" (tope 6) — meses CALENDARIO completos, más los
     * días sueltos del mes incompleto ÷ 30. Confirmado con RRHH set. 2026,
     * comparando contra un cálculo manual: usar días÷30 de forma continua
     * sobrecuenta cuando el periodo incluye meses de 31 días (ej. un cese
     * el 30/09 tras un periodo desde el 01/07 son 3 meses exactos —
     * jul/ago/sep—, no 3.07, porque jul y ago tienen 31 días).
     * También calcula el promedio de comisiones/horas extra de los meses
     * del semestre [$inicioSemestre, $finSemestre] que tengan liquidación
     * mensual registrada — misma regla de "al menos 3 de 6" que
     * gratificación/CTS regulares.
     *
     * @return array{0: float, 1: float, 2: float} [mesesComputables, promedioComisiones, promedioHorasExtra]
     */
    private function prorrateoTrunca(Employee $empleado, Carbon $desde, Carbon $fechaCese, Carbon $inicioSemestre, Carbon $finSemestre): array
    {
        $finInclusive     = $fechaCese->copy()->addDay();
        // diffInMonths() puede devolver decimales (ej. 2.5) — se trunca a
        // meses ENTEROS completos; los días sueltos del mes incompleto se
        // suman aparte, ÷30, para no contarlos dos veces.
        $mesesCompletos     = max(0, (int) floor($desde->diffInMonths($finInclusive)));
        $fechaTrasCompletos = $desde->copy()->addMonths($mesesCompletos);
        $diasRestantes      = max(0, $fechaTrasCompletos->diffInDays($finInclusive));
        $mesesComputables   = min(6.0, round($mesesCompletos + $diasRestantes / 30, 2));

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
