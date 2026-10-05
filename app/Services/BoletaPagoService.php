<?php

namespace App\Services;

use App\Models\AfpTasa;
use App\Models\CtsDeposito;
use App\Models\Gratificacion;
use App\Models\LiquidacionCese;
use App\Models\PlanillaLiquidacion;
use App\Models\Utilidad;
use Carbon\Carbon;

class BoletaPagoService
{
    /**
     * Arma el array de datos que necesita la boleta a partir de una liquidación.
     * Todo lo que no existe aún en el sistema (tipo_trabajador, cuspp) sale
     * en blanco — es responsabilidad del usuario completarlo manualmente
     * hasta que se agregue el campo, o lo agregamos si lo confirman.
     *
     * Si el periodo de la liquidación tiene una gratificación, una
     * participación de utilidades, o una liquidación por cese calculada,
     * sus montos se agregan a la misma boleta — se pagan juntos el mismo
     * mes, no en un documento aparte.
     *
     * La CTS (regular o trunca en un cese), en cambio, NO se suma a
     * neto_pagar: por ley se deposita directo a la cuenta CTS del
     * trabajador en el banco que él eligió, no se paga junto al sueldo.
     * Aparece solo como dato informativo y para declarar el código PLAME
     * 0904 (confirmado por RRHH, set. 2026).
     */
    public function datosBoleta(PlanillaLiquidacion $l): array
    {
        $empleado = $l->employee;

        $gratificacion = Gratificacion::where('employee_id', $l->employee_id)
            ->where('periodo', $l->periodo)
            ->first();

        $cts = CtsDeposito::where('employee_id', $l->employee_id)
            ->where('periodo', $l->periodo)
            ->first();

        $utilidad = Utilidad::where('employee_id', $l->employee_id)
            ->where('periodo', $l->periodo)
            ->first();

        // La liquidación por cese no guarda un campo 'periodo' — se ubica
        // por año-mes de fecha_cese, que debe coincidir con el mes de esta
        // liquidación mensual (el cese se paga en la planilla de ese mes).
        $cese = LiquidacionCese::where('employee_id', $l->employee_id)
            ->whereRaw("DATE_FORMAT(fecha_cese, '%Y-%m') = ?", [$l->periodo])
            ->first();

        $situacion = ($empleado?->fecha_cese && Carbon::parse($empleado->fecha_cese)->lte(now()))
            ? 'CESADO'
            : 'ACTIVO O SUBSIDIADO';

        return [
            // ── Cabecera ─────────────────────────────────────────────────────
            'ruc'              => $l->company->ruc ?? '',
            'empleador'        => $l->company->razon_social ?? '',
            'periodo'          => $this->periodoFormato($l->periodo),
            'dni'              => $l->dni,
            'nombres_completos' => trim($l->apellidos . ' ' . $l->nombres),
            'situacion'        => $situacion,
            'fecha_ingreso'    => $empleado?->fecha_ingreso ? Carbon::parse($empleado->fecha_ingreso)->format('d/m/Y') : '',
            'tipo_trabajador'  => 'EMPLEADO', // Único tipo soportado hoy
            'regimen_pensionario' => $l->sistema_pensiones_label,
            'cuspp'            => $empleado?->cuspp ?? '',

            // ── Asistencia ───────────────────────────────────────────────────
            'dias_laborados'    => $l->dias_trabajados,
            'dias_no_laborados' => $l->dias_falta,
            'dias_subsidiados'  => 0, // Pendiente: no se registra subsidio hoy
            'condicion'         => 'Domiciliado',
            'jornada_horas'     => 8,
            'jornada_minutos'   => 0,
            'sobretiempo_horas'   => (int) floor($l->horas_extra_diurnas + $l->horas_extra_nocturnas),
            'sobretiempo_minutos' => (int) round(fmod($l->horas_extra_diurnas + $l->horas_extra_nocturnas, 1) * 60),

            // ── Ingresos (código PLAME => [concepto, monto]) ───────────────────
            'ingresos' => array_filter([
                '0121' => ['REMUNERACIÓN O JORNAL BÁSICO', $l->sueldo_proporcional],
                // 0118 se usa tanto para vacaciones gozadas en un mes normal
                // ($l->vacaciones) como para la remuneración vacacional
                // pendiente en un cese — se suman por si algún mes coinciden
                // (poco probable, pero no deben pisarse entre sí).
                '0118' => ($l->vacaciones + ($cese->remuneracion_vacacional_pendiente ?? 0)) > 0
                    ? ['REMUNERACIÓN VACACIONAL', $l->vacaciones + ($cese->remuneracion_vacacional_pendiente ?? 0)]
                    : null,
                '0105' => $l->horas_extra_diurnas > 0
                    ? ['TRABAJO EN SOBRETIEMPO (HORAS EXTRAS) 25%', $l->importe_horas_extra_diurnas]
                    : null,
                '0106' => $l->horas_extra_nocturnas > 0
                    ? ['TRABAJO EN SOBRETIEMPO (HORAS EXTRAS) 35%', $l->importe_horas_extra_nocturnas]
                    : null,
                '0103' => $l->comisiones > 0
                    ? ['COMISIONES O DESTAJO', $l->comisiones]
                    : null,
                '0201' => $l->asignacion_familiar > 0
                    ? ['ASIGNACIÓN FAMILIAR', $l->asignacion_familiar]
                    : null,
                '0909' => $l->bono_movilidad > 0
                    ? ['MOV SUPEDIT A ASIST CUBRE TRASLADO', $l->bono_movilidad]
                    : null,
                '1007' => $l->bono_encargatura > 0
                    ? ['BONO POR ENCARGATURA', $l->bono_encargatura]
                    : null,
                '0403' => $l->bonos_especiales > 0
                    ? ['GRATIFICACIONES EXTRAORDINARIAS', $l->bonos_especiales]
                    : null,
                // Códigos confirmados por RRHH (set. 2026) contra el catálogo
                // real de este RUC en SUNAT.
                '0406' => $gratificacion && $gratificacion->monto_gratificacion > 0
                    ? ["GRATIFICACIÓN {$gratificacion->tipo}", $gratificacion->monto_gratificacion]
                    : null,
                '0312' => $gratificacion && $gratificacion->bonificacion_extraordinaria > 0
                    ? ['BONIFICACIÓN EXTRAORDINARIA (LEY 29351)', $gratificacion->bonificacion_extraordinaria]
                    : null,
                '0915' => $l->subsidio_maternidad > 0
                    ? ['SUBSIDIOS POR MATERNIDAD', $l->subsidio_maternidad]
                    : null,
                '0916' => $l->subsidio_enfermedad > 0
                    ? ['SUBSIDIO INCAPACIDAD POR ENFERMEDAD', $l->subsidio_enfermedad]
                    : null,
                // 0904 y 0910 confirmados por RRHH (set. 2026). 0904 suma la
                // CTS regular del periodo (si la hay) más la CTS trunca de
                // una liquidación por cese en el mismo mes (rara vez
                // coinciden ambas, pero por si acaso no se pisan entre sí).
                '0904' => (($cts->monto_cts ?? 0) + ($cese->monto_cts_trunca ?? 0)) > 0
                    ? ['COMPENSACIÓN POR TIEMPO DE SERVICIOS', ($cts->monto_cts ?? 0) + ($cese->monto_cts_trunca ?? 0)]
                    : null,
                '0910' => $utilidad && $utilidad->monto_pagado > 0
                    ? ['PARTICIPACIÓN EN LAS UTILIDADES', $utilidad->monto_pagado]
                    : null,
                // ── Conceptos de liquidación por cese, códigos confirmados
                // por RRHH (set. 2026). "Devolución de 5ta" (1002) sigue sin
                // implementar — es el único que falta de la lista de RRHH.
                '0407' => $cese && $cese->monto_gratificacion_trunca > 0
                    ? ['GRATIFICACIÓN PROPORCIONAL (CESE)', $cese->monto_gratificacion_trunca]
                    : null,
                '0313' => $cese && $cese->bonificacion_extraordinaria_trunca > 0
                    ? ['BONIFICACIÓN PROPORCIONAL (CESE)', $cese->bonificacion_extraordinaria_trunca]
                    : null,
                '0114' => $cese && $cese->monto_vacaciones_truncas > 0
                    ? ['VACACIONES TRUNCAS', $cese->monto_vacaciones_truncas]
                    : null,
                // Periodo completo (30 días) ya ganado y no gozado dentro
                // del año siguiente — distinto de vacaciones truncas.
                // (el monto ya se agregó a la clave 0118 más arriba)
                '0504' => $cese && $cese->indemnizacion_vacacional > 0
                    ? ['INDEMNIZACIÓN POR VACACIONES NO GOZADAS', $cese->indemnizacion_vacacional]
                    : null,
                // Indemnización por DESPIDO ARBITRARIO — sin código PLAME
                // confirmado todavía (no es lo mismo que 0504, que es la
                // indemnización por vacaciones no gozadas). Se muestra en
                // la boleta pero no se declara en PLAME hasta tener el
                // código correcto.
                'INDEMNIZACION_CESE' => $cese && $cese->indemnizacion > 0
                    ? ['INDEMNIZACIÓN POR DESPIDO ARBITRARIO', $cese->indemnizacion]
                    : null,
            ]),

            // ── Descuentos ──────────────────────────────────────────────────
            'descuentos' => array_filter([
                '0704' => $l->descuento_tardanzas > 0
                    ? ['TARDANZAS', $l->descuento_tardanzas]
                    : null,
                '0705' => $l->descuento_faltas > 0
                    ? ['INASISTENCIAS', $l->descuento_faltas]
                    : null,
                '0706' => ($l->otros_descuentos + $l->eps_descuento_trabajador) > 0
                    ? ['OTROS DESC NO DEDUC DE BASE IMPONIB (préstamos/otros/EPS)', $l->otros_descuentos + $l->eps_descuento_trabajador]
                    : null,
                '0701' => $l->adelanto > 0
                    ? ['ADELANTO', $l->adelanto]
                    : null,
            ]),

            // ── Aportes del trabajador ────────────────────────────────────────
            'aportes_trabajador' => $this->aportesTrabajador($l, $cese),

            // Del cese solo se suman los conceptos que se pagan en efectivo:
            // gratificación proporcional + su bonificación, vacaciones
            // truncas, remuneración vacacional pendiente + su indemnización,
            // e indemnización por despido (si aplica). La CTS trunca queda
            // fuera — va depositada, igual que la CTS regular.
            'neto_pagar' => round(
                (float) $l->neto_pagar
                + ($gratificacion->monto_total ?? 0)
                + ($utilidad->monto_pagado ?? 0)
                + ($cese->monto_gratificacion_trunca ?? 0)
                + ($cese->bonificacion_extraordinaria_trunca ?? 0)
                + ($cese->monto_vacaciones_truncas ?? 0)
                + ($cese->remuneracion_vacacional_pendiente ?? 0)
                + ($cese->indemnizacion_vacacional ?? 0)
                + ($cese->indemnizacion ?? 0)
                - ($cese->descuento_afp_vacaciones ?? 0),
                2
            ),

            // ── Aportes del empleador (informativo) ────────────────────────────
            'aportes_empleador' => array_filter([
                '0803' => $l->seguro_vida_empleador > 0
                    ? ['PÓLIZA DE SEGURO - D. LEG. 688', $l->seguro_vida_empleador]
                    : null,
                // EsSalud mensual + el de las vacaciones del cese (se declara
                // junto en la misma boleta, pedido de Cielo oct. 2026).
                '0804' => ($l->essalud_empleador + ($cese->aporte_essalud_vacaciones ?? 0)) > 0
                    ? ['ESSALUD (REGULAR CBSSP AGRAR/AC) TRAB', round($l->essalud_empleador + ($cese->aporte_essalud_vacaciones ?? 0), 2)]
                    : null,
            ]),

            // EPS — sin código PLAME confirmado todavía (pendiente validar
            // con el contador). Se muestra en la boleta pero NO se incluye
            // en el archivo E18 hasta tener el código correcto.
            'eps' => $l->eps_descuento_trabajador > 0 ? [
                'descuento_trabajador' => $l->eps_descuento_trabajador,
                'aporte_empresa'       => $l->eps_aporte_empresa,
                'credito'              => $l->eps_credito,
            ] : null,
        ];
    }

    private function aportesTrabajador(PlanillaLiquidacion $l, ?LiquidacionCese $cese = null): array
    {
        $aportes = [];

        // AFP/ONP de las vacaciones del cese (truncas + remuneración
        // vacacional pendiente): la liquidación guarda solo el total, así
        // que se reparte por código PLAME según las tasas de la AFP,
        // ajustando para que la suma coincida exacto con ese total.
        $vac = ['0601' => 0.0, '0606' => 0.0, '0608' => 0.0, 'onp' => 0.0];
        $totalVac = (float) ($cese->descuento_afp_vacaciones ?? 0);

        if ($totalVac > 0) {
            if (str_starts_with($l->sistema_pensiones, 'afp_') && ($tasa = AfpTasa::vigentePara($l->sistema_pensiones))) {
                $base = (float) $cese->monto_vacaciones_truncas + (float) $cese->remuneracion_vacacional_pendiente;
                $crudo = [
                    '0608' => $base * (float) $tasa->aporte_obligatorio,
                    '0601' => $l->employee?->aplica_comision_flujo_afp ? $base * (float) $tasa->comision_flujo : 0.0,
                    '0606' => $base * (float) $tasa->prima_seguro,
                ];
                $sumaCruda = array_sum($crudo);

                if ($sumaCruda > 0) {
                    foreach ($crudo as $cod => $v) {
                        $vac[$cod] = round($v / $sumaCruda * $totalVac, 2);
                    }
                    $vac['0608'] = round($vac['0608'] + ($totalVac - ($vac['0601'] + $vac['0606'] + $vac['0608'])), 2);
                } else {
                    $vac['0608'] = $totalVac;
                }
            } else {
                $vac['onp'] = $totalVac;
            }
        }

        if (str_starts_with($l->sistema_pensiones, 'afp_')) {
            $comision = round($l->afp_comision_flujo + $vac['0601'], 2);
            $prima    = round($l->afp_prima_seguro + $vac['0606'], 2);
            $aporte   = round($l->afp_aporte_obligatorio + $vac['0608'], 2);

            $aportes = array_filter([
                '0601' => $comision > 0 ? ['COMISIÓN AFP PORCENTUAL', $comision] : null,
                '0606' => $prima > 0    ? ['PRIMA DE SEGURO AFP', $prima] : null,
                '0608' => $aporte > 0   ? ['SPP - APORTACIÓN OBLIGATORIA', $aporte] : null,
            ]);
        } else {
            $onp = round($l->descuento_pension + $vac['onp'], 2);

            $aportes = array_filter([
                '0607' => $onp > 0 ? ['SISTEMA NACIONAL DE PENSIONES - DL 19990', $onp] : null,
            ]);
        }

        // Renta 5ta (0605) — aplica a AMBOS regímenes, faltaba antes.
        if ($l->descuento_5ta_categoria > 0) {
            $aportes['0605'] = ['RENTA QUINTA CATEGORÍA - RETENCIONES', $l->descuento_5ta_categoria];
        }

        return $aportes;
    }

    private function periodoFormato(string $periodo): string
    {
        [$year, $month] = explode('-', $periodo);
        return "{$month}/{$year}";
    }
}
