<?php

namespace App\Services;

use App\Models\PlanillaLiquidacion;
use Illuminate\Support\Collection;

class PlameExportService
{
    /**
     * Genera el contenido del archivo E14 (.jor) — Jornada Laboral por trabajador.
     * Formato oficial SUNAT Anexo 3, Estructura 14.
     *
     * Nombre de archivo esperado por el PDT: 0601{AAAA}{MM}{RUC}.jor
     */
    public function generarE14Jornada(Collection $liquidaciones): string
    {
        $lineas = [];

        foreach ($liquidaciones as $l) {
            $empleado = $l->employee;
            if (!$empleado) {
                continue;
            }

            // Horas ordinarias REALES del mes (confirmado con contador:
            // son las horas efectivamente laboradas, sin sumar tardanzas
            // ni faltas). Sumamos directo de attendance_records, que ya
            // calcula esto neto por día con el fix de refrigerio/tardanza.
            $minutosOrdinariosTotales = (int) round(
                \App\Models\AttendanceRecord::where('employee_id', $empleado->id)
                    ->whereBetween('fecha', [
                        \Carbon\Carbon::parse($l->periodo . '-01')->startOfMonth()->toDateString(),
                        \Carbon\Carbon::parse($l->periodo . '-01')->endOfMonth()->toDateString(),
                    ])
                    ->sum('horas_ordinarias') * 60
            );

            $horasOrdinarias   = min(360, intdiv($minutosOrdinariosTotales, 60));
            $minutosOrdinarios = min(59, $minutosOrdinariosTotales % 60);

            $horasExtraTotales   = $l->horas_extra_diurnas + $l->horas_extra_nocturnas;
            $horasExtra          = min(360, (int) floor($horasExtraTotales));
            $minutosExtra        = min(59, (int) round(($horasExtraTotales - $horasExtra) * 60));

            $lineas[] = implode('|', [
                '01', // Tipo de documento: 01 = DNI
                $l->dni,
                $horasOrdinarias,
                $minutosOrdinarios,
                $horasExtra,
                $minutosExtra,
            ]);
        }

        return implode("\r\n", $lineas);
    }

    /**
     * Catálogo de conceptos REGISTRADOS para InProcess/Quantum en SUNAT
     * (reporte "Información Registrada" PDT601, RUC 20514706302, verificado
     * 24/ago/2026, confirmado por contador: mismos conceptos para ambas
     * empresas). NO es una lista genérica — es la lista real y oficial que
     * el PDT espera para este RUC específico.
     *
     * Se declaran TODOS con 0 cuando no aplica ese mes (confirmado contra
     * archivo .rem real: el catálogo completo va siempre, sin omitir ceros).
     *
     * Conceptos marcados [SIN CALCULAR HOY] existen en el catálogo SUNAT
     * pero el sistema aún no los calcula (gratificaciones, liquidaciones,
     * utilidades — módulos pendientes). Van con 0 hasta que se implementen.
     */
    const CATALOGO_INGRESOS_DESCUENTOS_E18 = [
        // 0100 - Ingresos
        '0103', // Comisiones o destajo
        '0104', // Comisiones eventuales [SIN CALCULAR HOY — hoy todo va a 0103]
        '0105', // Horas extra 25%
        '0106', // Horas extra 35%
        '0107', // Trabajo en feriado/descanso [SIN CALCULAR HOY]
        '0114', // Vacaciones truncas [SIN CALCULAR HOY]
        '0115', // Remuneración día descanso/feriados [SIN CALCULAR HOY]
        '0117', // Compensación vacacional [SIN CALCULAR HOY]
        '0118', // Remuneración vacacional [SIN CALCULAR HOY]
        '0121', // Remuneración/jornal básico
        '0122', // Remuneración permanente [no aplica, alternativo a 0121]
        '0129', // Estipendio interno ciencias salud [no aplica normalmente]
        // 0200 - Asignaciones
        '0201', // Asignación familiar [SIN CALCULAR HOY]
        '0202', // Asignación/bonificación educación [SIN CALCULAR HOY]
        // 0300 - Bonificaciones
        '0303', '0306', '0309', '0312', '0313', // [SIN CALCULAR HOY, salvo bono especial genérico]
        // 0400 - Gratificaciones/Aguinaldos
        '0402', // Gratificación (Fiestas Patrias/Navidad) — calculado desde GratificacionResource
        '0403', // Gratificaciones extraordinarias (bono ad-hoc, bonos_especiales)
        '0406', // Bonificación extraordinaria 9% (Ley 29351) — calculado desde GratificacionResource
        '0405', '0407', '0411', // [SIN CALCULAR HOY — gratificación TRUNCA (cese antes de completar semestre) pendiente]
        // 0500 - Indemnizaciones
        '0501', '0504', // [SIN CALCULAR HOY — módulo de liquidaciones pendiente]
        // 0700 - Descuentos al trabajador
        '0701', // Adelanto [SIN CALCULAR HOY]
        '0702', // Cuota sindical [SIN CALCULAR HOY]
        '0703', // Descuento por mandato judicial [SIN CALCULAR HOY]
        '0704', // Tardanzas
        '0705', // Inasistencias
        '0706', // Otros descuentos [SIN CALCULAR HOY]
        // 0900 - Conceptos varios
        '0902', // Bono de productividad [usamos bonos_especiales aquí]
        '0903', // Canasta navidad [SIN CALCULAR HOY]
        '0904', // CTS [SIN CALCULAR HOY]
        '0907', // Licencia con goce de haber [SIN CALCULAR HOY]
        '0909', // Movilidad supeditada [SIN CALCULAR HOY]
        '0910', // Participación utilidades [SIN CALCULAR HOY — módulo de utilidades pendiente]
        '0915', // Subsidio maternidad [SIN CALCULAR HOY]
        '0916', // Subsidio incapacidad [SIN CALCULAR HOY]
        '0928', // Devolución exceso retención 5ta [SIN CALCULAR HOY]
        // 1000 - Otros conceptos (personalizados de InProcess)
        '1001', // Reintegros [SIN CALCULAR HOY]
        '1002', // Devolución 5ta categoría [SIN CALCULAR HOY]
        '1007', // Bono por encargatura
    ];

    /**
     * Tributos y aportes — no están en la lista de "conceptos personalizados"
     * porque no son seleccionables, son estándar. Se declaran según régimen
     * pensionario del trabajador (AFP vs ONP).
     */
    const CATALOGO_TRIBUTOS_E18 = ['0601', '0605', '0606', '0607', '0608'];

    public function generarE18Ingresos(\Illuminate\Support\Collection $liquidaciones, \App\Services\BoletaPagoService $boletaService): string
    {
        $lineas = [];

        foreach ($liquidaciones as $l) {
            $empleado = $l->employee;
            if (!$empleado) {
                continue;
            }

            $datos = $boletaService->datosBoleta($l);

            $montosPorCodigo = [];
            foreach ($datos['ingresos'] as $codigo => [, $monto]) {
                $montosPorCodigo[$codigo] = $monto;
            }
            foreach ($datos['descuentos'] as $codigo => [, $monto]) {
                $montosPorCodigo[$codigo] = $monto;
            }
            foreach ($datos['aportes_trabajador'] as $codigo => [, $monto]) {
                $montosPorCodigo[$codigo] = $monto;
            }
            // 0605 (Renta 5ta) no viene de aportes_trabajador hoy — se mapea
            // directo desde descuento_5ta_categoria de la liquidación.
            $montosPorCodigo['0605'] = (float) $l->descuento_5ta_categoria;

            $catalogoCompleto = array_merge(self::CATALOGO_INGRESOS_DESCUENTOS_E18, self::CATALOGO_TRIBUTOS_E18);

            foreach ($catalogoCompleto as $codigo) {
                $monto = $montosPorCodigo[$codigo] ?? 0;
                $lineas[] = $this->lineaE18($empleado->dni, $codigo, $monto);
            }
        }

        return implode("\r\n", $lineas);
    }

    private function lineaE18(string $dni, string $codigo, float $monto): string
    {
        // Devengado y pagado siempre iguales — confirmado contra archivo
        // real: en 1,115 líneas de ejemplo nunca difirieron.
        $montoFormateado = $this->formatearMontoPlame($monto);

        return implode('|', [
            '01', // Tipo de documento: 01 = DNI
            $dni,
            $codigo,
            $montoFormateado,
            $montoFormateado,
        ]);
    }

    /**
     * Formatea montos como en el archivo real: sin ceros decimales de más
     * cuando el número es entero (ej. "1125" no "1125.00"), pero con
     * decimales cuando los hay (ej. "30.83"). Los montos en 0 se escriben
     * literal "0", no "0.00".
     */
    private function formatearMontoPlame(float $monto): string
    {
        if ($monto == 0) {
            return '0';
        }

        // Si no tiene parte decimal significativa, sin punto.
        if ($monto == floor($monto)) {
            return (string) (int) $monto;
        }

        return rtrim(rtrim(number_format($monto, 2, '.', ''), '0'), '.');
    }

    public function nombreArchivoE18(string $periodo, string $rucEmpleador): string
    {
        [$year, $month] = explode('-', $periodo);
        return "0601{$year}{$month}{$rucEmpleador}.rem";
    }

    /**
     * Genera el contenido del archivo E15 (.snl) — Días subsidiados y otros
     * días no laborados por tipo de suspensión. Formato oficial SUNAT
     * Anexo 3, Estructura 15.
     *
     * Agrupa por empleado + código de suspensión, sumando los días del mes
     * (un empleado puede tener varias líneas si tuvo motivos distintos,
     * ej: 2 días de falta + 3 días de licencia con goce).
     */
    public function generarE15DiasNoLaborados(\Illuminate\Support\Collection $attendanceRecords): string
    {
        $agrupado = $attendanceRecords
            ->whereNotNull('motivo_suspension_plame')
            ->groupBy(fn ($r) => $r->employee_id . '|' . $r->motivo_suspension_plame);

        $lineas = [];

        foreach ($agrupado as $grupo) {
            $primero  = $grupo->first();
            $empleado = $primero->employee;
            if (!$empleado) {
                continue;
            }

            $dias = min(31, $grupo->count());

            $lineas[] = implode('|', [
                '01', // Tipo de documento: 01 = DNI
                $empleado->dni,
                $primero->motivo_suspension_plame,
                $dias,
            ]);
        }

        return implode("\r\n", $lineas);
    }

    public function nombreArchivoE15(string $periodo, string $rucEmpleador): string
    {
        [$year, $month] = explode('-', $periodo);
        return "0601{$year}{$month}{$rucEmpleador}.snl";
    }

    /**
     * Nombre de archivo oficial esperado por el PDT PLAME para la estructura 14.
     */
    public function nombreArchivoE14(string $periodo, string $rucEmpleador): string
    {
        [$year, $month] = explode('-', $periodo);
        return "0601{$year}{$month}{$rucEmpleador}.jor";
    }


}
