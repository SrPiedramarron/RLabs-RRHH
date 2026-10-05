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

            $lineas[] = $this->lineaPlame([
                '01', // Tipo de documento: 01 = DNI
                $l->dni,
                $horasOrdinarias,
                $minutosOrdinarios,
                $horasExtra,
                $minutosExtra,
            ]);
        }

        return $this->unirLineasPlame($lineas);
    }

    /**
     * Arma una línea del formato SUNAT: campos separados por "|" Y CON UN
     * "|" FINAL después del último campo — confirmado contra un archivo
     * real ya aceptado por SUNAT (oct. 2026, PLAME Agosto 2026 de otra
     * empresa que Cielo consiguió): cada línea real termina en "campo|\r\n",
     * no en "campo\r\n". Sin ese pipe final, el PDT rechaza TODAS las filas
     * con "la cantidad de columnas no coincide" — esta era la causa real
     * del rechazo total reportado por SUNAT, no un problema de catálogo.
     */
    private function lineaPlame(array $campos): string
    {
        return implode('|', $campos) . '|';
    }

    /**
     * Une las líneas con "\r\n" Y agrega un "\r\n" final después de la
     * última línea — el archivo real de referencia también termina así.
     */
    private function unirLineasPlame(array $lineas): string
    {
        if (empty($lineas)) {
            return '';
        }

        return implode("\r\n", $lineas) . "\r\n";
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
        '0114', // Vacaciones truncas (cese) — calculado desde LiquidacionCeseResource (confirmado por RRHH)
        '0115', // Remuneración día descanso/feriados [SIN CALCULAR HOY]
        '0117', // Compensación vacacional [SIN CALCULAR HOY]
        '0118', // Remuneración vacacional — vacaciones gozadas en el mes normal, y remuneración vacacional pendiente en un cese (periodo completo no gozado dentro del año siguiente) — calculado, confirmado por RRHH
        '0121', // Remuneración/jornal básico
        '0122', // Remuneración permanente [no aplica, alternativo a 0121]
        '0129', // Estipendio interno ciencias salud [no aplica normalmente]
        // 0200 - Asignaciones
        '0201', // Asignación familiar [SIN CALCULAR HOY]
        '0202', // Asignación/bonificación educación [SIN CALCULAR HOY]
        // 0300 - Bonificaciones
        '0312', // Bonificación extraordinaria 9% (Ley 29351) — calculado desde GratificacionResource (confirmado por RRHH)
        '0313', // Bonificación proporcional 9% (cese) — calculado desde LiquidacionCeseResource (confirmado por RRHH)
        '0303', '0306', '0309', // [SIN CALCULAR HOY, salvo bono especial genérico]
        // 0400 - Gratificaciones/Aguinaldos
        '0406', // Gratificación (Fiestas Patrias/Navidad) — calculado desde GratificacionResource (confirmado por RRHH)
        '0407', // Gratificación proporcional (cese) — calculado desde LiquidacionCeseResource (confirmado por RRHH)
        '0403', // Gratificaciones extraordinarias (bono ad-hoc, bonos_especiales)
        '0402', '0405', '0411', // [SIN CALCULAR HOY — no confirmados por RRHH, uso no identificado todavía]
        // 0500 - Indemnizaciones
        '0501', // [SIN CALCULAR HOY — indemnización por despido arbitrario ya se calcula en LiquidacionCeseResource, pero RRHH no ha confirmado su código PLAME (0504 es OTRO concepto, ver abajo). Se muestra en la boleta sin declarar en PLAME hasta confirmarlo]
        '0504', // Indemnización por vacaciones NO GOZADAS (cese) — periodo completo ganado y no gozado dentro del año siguiente, DISTINTO de vacaciones truncas (0114) y de la indemnización por despido arbitrario (sin código aún) — calculado, confirmado por RRHH
        // 0700 - Descuentos al trabajador
        '0701', // Adelanto: adelantos manuales + quincena ya pagada (confirmado por el contador, oct. 2026)
        '0702', // Cuota sindical [SIN CALCULAR HOY]
        '0703', // Descuento por mandato judicial [SIN CALCULAR HOY]
        '0704', // Tardanzas
        '0705', // Inasistencias
        '0706', // Otros descuentos [SIN CALCULAR HOY]
        // 0900 - Conceptos varios
        '0902', // Bono de productividad [usamos bonos_especiales aquí]
        '0903', // Canasta navidad [SIN CALCULAR HOY]
        '0904', // CTS (regular o trunca en un cese) — calculado desde CtsResource / LiquidacionCeseResource (confirmado por RRHH)
        '0907', // Licencia con goce de haber [SIN CALCULAR HOY]
        '0909', // Movilidad supeditada [SIN CALCULAR HOY]
        '0910', // Participación utilidades — calculado desde UtilidadResource (confirmado por RRHH)
        '0915', // Subsidio maternidad [SIN CALCULAR HOY]
        '0916', // Subsidio incapacidad [SIN CALCULAR HOY]
        '0928', // Devolución exceso retención 5ta [SIN CALCULAR HOY]
        // 1000 - Otros conceptos (personalizados de InProcess)
        '1001', // Reintegros [SIN CALCULAR HOY]
        '1002', // Devolución de 5ta categoría (confirmado por RRHH) — el sistema no calcula este ajuste todavía [SIN CALCULAR HOY]
        '1007', // Bono por encargatura
    ];

    /**
     * Tributos y aportes — no están en la lista de "conceptos personalizados"
     * porque no son seleccionables, son estándar.
     *
     * 0605 (Renta 5ta) va siempre, sin importar el régimen pensionario.
     *
     * 0601, 0606 y 0608 el manual SUNAT (Anexo 3, Estructura 18) dice
     * explícitamente que "se incluyen cuando el régimen pensionario
     * corresponda al Sistema Privado de Pensiones" — es decir, SOLO para
     * trabajadores con AFP, nunca para ONP. Corregido oct. 2026 (antes se
     * enviaban siempre, para todos).
     *
     * 0607 el mismo manual lo tiene en la lista de códigos que NO se deben
     * incluir nunca en este archivo — se había agregado por error.
     */
    const CATALOGO_TRIBUTOS_E18 = ['0605'];

    const CATALOGO_TRIBUTOS_AFP_E18 = ['0601', '0606', '0608'];

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

            $catalogoCompleto = array_merge(
                self::CATALOGO_INGRESOS_DESCUENTOS_E18,
                self::CATALOGO_TRIBUTOS_E18,
                str_starts_with((string) $empleado->sistema_pensiones, 'afp_') ? self::CATALOGO_TRIBUTOS_AFP_E18 : []
            );

            foreach ($catalogoCompleto as $codigo) {
                $monto = $montosPorCodigo[$codigo] ?? 0;
                $lineas[] = $this->lineaE18($empleado->dni, $codigo, $monto);
            }
        }

        return $this->unirLineasPlame($lineas);
    }

    private function lineaE18(string $dni, string $codigo, float $monto): string
    {
        // Devengado y pagado siempre iguales — confirmado contra archivo
        // real: en 1,115 líneas de ejemplo nunca difirieron.
        $montoFormateado = $this->formatearMontoPlame($monto);

        return $this->lineaPlame([
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

            $lineas[] = $this->lineaPlame([
                '01', // Tipo de documento: 01 = DNI
                $empleado->dni,
                $primero->motivo_suspension_plame,
                $dias,
            ]);
        }

        return $this->unirLineasPlame($lineas);
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
