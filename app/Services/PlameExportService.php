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
                continue; // no debería pasar, pero no rompemos el archivo por un registro huérfano
            }

            $horasOrdinarias   = (int) floor($l->dias_trabajados > 0 ? min(360, $this->horasOrdinariasDelMes($l)) : 0);
            $minutosOrdinarios = $this->minutosOrdinariosDelMes($l);
            $horasExtra        = (int) floor($l->horas_extra_diurnas + $l->horas_extra_nocturnas);
            $minutosExtra      = (int) round((($l->horas_extra_diurnas + $l->horas_extra_nocturnas) - $horasExtra) * 60);

            // Topes duros que exige la estructura: horas máx 360, minutos máx 59
            $horasOrdinarias   = min(360, max(0, $horasOrdinarias));
            $minutosOrdinarios = min(59, max(0, $minutosOrdinarios));
            $horasExtra        = min(360, max(0, $horasExtra));
            $minutosExtra      = min(59, max(0, $minutosExtra));

            $lineas[] = implode('|', [
                '01', // Tipo de documento: 01 = DNI (único tipo soportado hoy en el sistema)
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

    /**
     * Convierte horas_ordinarias de la liquidación (ya en formato decimal mensual)
     * a horas enteras para la estructura. TODO: verificar con datos reales si
     * PlanillaLiquidacion guarda horas ordinarias totales del mes o hay que
     * sumarlas desde AttendanceRecord — ajustar según corresponda.
     */
    private function horasOrdinariasDelMes(PlanillaLiquidacion $l): float
    {
        // Aproximación: 8h por día trabajado. Placeholder hasta confirmar
        // fuente exacta de horas ordinarias mensuales reales.
        return $l->dias_trabajados * 8;
    }

    private function minutosOrdinariosDelMes(PlanillaLiquidacion $l): int
    {
        return 0; // placeholder — ajustar si se necesita precisión de minutos
    }
}
