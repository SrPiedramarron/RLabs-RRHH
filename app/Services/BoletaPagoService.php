<?php

namespace App\Services;

use App\Models\PlanillaLiquidacion;
use Carbon\Carbon;

class BoletaPagoService
{
    /**
     * Arma el array de datos que necesita la boleta a partir de una liquidación.
     * Todo lo que no existe aún en el sistema (tipo_trabajador, cuspp) sale
     * en blanco — es responsabilidad del usuario completarlo manualmente
     * hasta que se agregue el campo, o lo agregamos si lo confirman.
     */
    public function datosBoleta(PlanillaLiquidacion $l): array
    {
        $empleado = $l->employee;

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
            'cuspp'            => '', // Pendiente: no existe en el sistema aún

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
            'aportes_trabajador' => $this->aportesTrabajador($l),

            'neto_pagar' => $l->neto_pagar,

            // ── Aportes del empleador (informativo) ────────────────────────────
            'aportes_empleador' => array_filter([
                '0803' => $l->seguro_vida_empleador > 0
                    ? ['PÓLIZA DE SEGURO - D. LEG. 688', $l->seguro_vida_empleador]
                    : null,
                '0804' => $l->essalud_empleador > 0
                    ? ['ESSALUD (REGULAR CBSSP AGRAR/AC) TRAB', $l->essalud_empleador]
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

    private function aportesTrabajador(PlanillaLiquidacion $l): array
    {
        if (str_starts_with($l->sistema_pensiones, 'afp_')) {
            return array_filter([
                '0601' => $l->afp_comision_flujo > 0 ? ['COMISIÓN AFP PORCENTUAL', $l->afp_comision_flujo] : null,
                '0606' => $l->afp_prima_seguro > 0   ? ['PRIMA DE SEGURO AFP', $l->afp_prima_seguro] : null,
                '0608' => $l->afp_aporte_obligatorio > 0 ? ['SPP - APORTACIÓN OBLIGATORIA', $l->afp_aporte_obligatorio] : null,
            ]);
        }

        // ONP: todo va en un solo concepto (código 0607, no está en la plantilla
        // por defecto pero se puede agregar si el cliente lo pide).
        return array_filter([
            '0607' => $l->descuento_pension > 0 ? ['SISTEMA NACIONAL DE PENSIONES - DL 19990', $l->descuento_pension] : null,
        ]);
    }

    private function periodoFormato(string $periodo): string
    {
        [$year, $month] = explode('-', $periodo);
        return "{$month}/{$year}";
    }
}
