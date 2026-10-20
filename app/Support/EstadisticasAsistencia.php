<?php

namespace App\Support;

use Carbon\Carbon;

class EstadisticasAsistencia
{
    /** Minutos → "dd:hh:mm" (día = 24 h). Sin minutos: guion. */
    public static function ddhhmm($minutos): string
    {
        $m = (int) round((float) $minutos);
        if ($m <= 0) {
            return '—';
        }

        return sprintf('%02d:%02d:%02d', intdiv($m, 1440), intdiv($m % 1440, 60), $m % 60);
    }

    /** Últimos 24 meses para el selector ('2026-10' => 'Octubre 2026'). */
    public static function meses(): array
    {
        $out = [];
        $d   = now()->startOfMonth();
        for ($i = 0; $i < 24; $i++) {
            $out[$d->format('Y-m')] = ucfirst($d->locale('es')->translatedFormat('F Y'));
            $d = $d->copy()->subMonth();
        }

        return $out;
    }

    /** Corte mensual de RRHH: del 26 del mes anterior al 25 del mes actual (desde el 26 ya cuenta el siguiente). */
    public static function corteMensual(?Carbon $hoy = null): array
    {
        $hoy ??= now();
        $fin = $hoy->day >= 26
            ? $hoy->copy()->addMonthNoOverflow()->day(25)
            : $hoy->copy()->day(25);

        return [$fin->copy()->subMonthNoOverflow()->day(26)->startOfDay(), $fin->startOfDay()];
    }

    /**
     * Puesto de un trabajador en el ranking de puntualidad de su empresa para el corte dado.
     * % = días puntuales / días registrados (igual que el ranking del panel). Empates comparten puesto.
     * Devuelve null si no tiene registros en el periodo.
     */
    public static function puestoPuntualidad(int $employeeId, int $companyId, Carbon $desde, Carbon $hasta): ?array
    {
        $filas = \Illuminate\Support\Facades\DB::table('attendance_records as a')
            ->join('employees as e', 'e.id', '=', 'a.employee_id')
            ->where('a.company_id', $companyId)
            ->where('e.active', true)
            ->whereBetween('a.fecha', [$desde->toDateString(), $hasta->toDateString()])
            ->groupBy('a.employee_id')
            ->selectRaw('a.employee_id, ROUND(100 * SUM(CASE WHEN a.minutos_tarde <= 0 AND a.estado NOT IN ("ausente","feriado") THEN 1 ELSE 0 END) / COUNT(*), 1) as pct')
            ->pluck('pct', 'employee_id');

        if (! $filas->has($employeeId)) {
            return null;
        }

        $mio = (float) $filas[$employeeId];

        return [
            'puesto' => 1 + $filas->filter(fn ($p) => (float) $p > $mio)->count(),
            'total'  => $filas->count(),
            'pct'    => $mio,
        ];
    }

    /** Rango [desde, hasta] a partir del filtro: fechas explícitas mandan sobre el mes. */
    public static function rango(?array $data): array
    {
        $data ??= [];
        if (! empty($data['desde']) || ! empty($data['hasta'])) {
            return [$data['desde'] ?: null, $data['hasta'] ?: null];
        }
        if (! empty($data['mes'])) {
            $ini = Carbon::createFromFormat('Y-m-d', $data['mes'] . '-01')->startOfMonth();

            return [$ini->toDateString(), $ini->copy()->endOfMonth()->toDateString()];
        }

        return [null, null];
    }
}
