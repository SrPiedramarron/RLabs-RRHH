<?php

namespace App\Support;

use Carbon\Carbon;

class EstadisticasAsistencia
{
    /**
     * Puntual: llegó a tiempo (estado presente, sin minutos de tardanza). Tarde: llegó con tardanza.
     * Las ausencias, feriados, descansos, permisos y vacaciones no cuentan ni a favor ni en contra.
     * % de puntualidad = puntuales ÷ (puntuales + tardes). Alias de tabla: a.
     */
    public static function sqlPuntual(): string
    {
        return "SUM(CASE WHEN a.estado = 'presente' AND a.minutos_tarde <= 0 THEN 1 ELSE 0 END)";
    }

    public static function sqlTarde(): string
    {
        return "SUM(CASE WHEN a.estado <> 'ausente' AND (a.estado = 'tarde' OR a.minutos_tarde > 0) THEN 1 ELSE 0 END)";
    }

    public static function sqlPct(): string
    {
        return 'ROUND(100 * ' . self::sqlPuntual() . ' / NULLIF(' . self::sqlPuntual() . ' + ' . self::sqlTarde() . ', 0), 1)';
    }

    /** ¿Se muestra la sección Beneficios en la app? Oculta por defecto hasta que RRHH la apruebe. */
    public static function beneficiosVisibles(): bool
    {
        try {
            return \App\Models\ParametroLegal::valor('beneficios_visibles') > 0;
        } catch (\Throwable) {
            return false;
        }
    }

    /** % mínimo de puntualidad para el beneficio (Administración → Parámetros Legales; 90 por defecto). */
    public static function umbralBeneficio(): float
    {
        try {
            return (float) \App\Models\ParametroLegal::valor('umbral_puntualidad_beneficio');
        } catch (\Throwable) {
            return 90.0;
        }
    }

    /** Días tarde, días puntual y % de un trabajador en un rango. pct es null si no hubo días contables. */
    public static function cumplimiento(int $employeeId, Carbon $desde, Carbon $hasta): array
    {
        $fila = \Illuminate\Support\Facades\DB::table('attendance_records as a')
            ->where('a.employee_id', $employeeId)
            ->whereBetween('a.fecha', [$desde->toDateString(), $hasta->toDateString()])
            ->selectRaw(self::sqlPuntual() . ' as puntual, ' . self::sqlTarde() . ' as tarde')
            ->first();

        $puntual = (int) ($fila->puntual ?? 0);
        $tarde   = (int) ($fila->tarde ?? 0);

        return [
            'puntual' => $puntual,
            'tarde'   => $tarde,
            'pct'     => ($puntual + $tarde) > 0 ? round(100 * $puntual / ($puntual + $tarde), 1) : null,
        ];
    }

    /** Cortes del 26 al 25 disponibles (últimos 12): clave 'YYYY-MM' = mes en que termina el corte. */
    public static function cortesDisponibles(): array
    {
        [, $actual] = self::corteMensual();
        $out = [];
        for ($i = 0; $i < 12; $i++) {
            $fin = $actual->copy()->subMonthsNoOverflow($i)->day(25);
            $ini = $fin->copy()->subMonthNoOverflow()->addDay();
            $out[$fin->format('Y-m')] = 'Corte ' . $fin->locale('es')->translatedFormat('F Y') . ' (' . $ini->format('d/m') . ' – ' . $fin->format('d/m') . ')';
        }

        return $out;
    }

    /** @return array{0: Carbon, 1: Carbon, 2: string} desde, hasta y clave elegida, según ?corte= (o ?desde=&hasta= si corte=custom) */
    public static function rangoDeRequest(\Illuminate\Http\Request $request): array
    {
        $cortes = self::cortesDisponibles();
        $clave  = (string) $request->query('corte', array_key_first($cortes));

        if ($clave === 'custom') {
            $f = function ($v) { try { return $v ? Carbon::parse($v) : null; } catch (\Throwable) { return null; } };
            $desde = $f($request->query('desde')) ?? now()->startOfMonth();
            $hasta = $f($request->query('hasta')) ?? now();
            if ($hasta->lt($desde)) {
                [$desde, $hasta] = [$hasta, $desde];
            }

            return [$desde->startOfDay(), $hasta->startOfDay(), 'custom'];
        }

        if (! isset($cortes[$clave])) {
            $clave = array_key_first($cortes);
        }
        $hasta = Carbon::createFromFormat('Y-m-d', $clave . '-25')->startOfDay();

        return [$hasta->copy()->subMonthNoOverflow()->addDay(), $hasta, $clave];
    }

    /**
     * Consulta base ÚNICA de todo lo que muestra puntualidad (dashboard, ranking del panel y ranking de la app),
     * para que los números sean idénticos en los tres. Alias: a = marcaciones, e = trabajador, l = sede, d = área.
     * Cuenta a todo trabajador con marcaciones en el periodo (aunque ya haya cesado) y excluye al personal
     * exonerado de registro (no sujeto a fiscalización). Fechas nulas = sin límite.
     */
    public static function consultaBase(?int $companyId, $desde = null, $hasta = null): \Illuminate\Database\Query\Builder
    {
        return \Illuminate\Support\Facades\DB::table('attendance_records as a')
            ->join('employees as e', 'e.id', '=', 'a.employee_id')
            ->leftJoin('locations as l', 'l.id', '=', 'e.location_id')
            ->leftJoin('departments as d', 'd.id', '=', 'e.department_id')
            ->where('e.exonerado_registro', false)
            ->when($companyId, fn ($q) => $q->where('a.company_id', $companyId))
            ->when($desde, fn ($q) => $q->whereDate('a.fecha', '>=', $desde instanceof Carbon ? $desde->toDateString() : $desde))
            ->when($hasta, fn ($q) => $q->whereDate('a.fecha', '<=', $hasta instanceof Carbon ? $hasta->toDateString() : $hasta));
    }

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
        $filas = self::consultaBase($companyId, $desde, $hasta)
            ->groupBy('a.employee_id')
            ->selectRaw('a.employee_id, ' . self::sqlPct() . ' as pct')
            ->pluck('pct', 'employee_id')
            ->filter(fn ($p) => $p !== null);

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

    /** Ranking de áreas de una empresa en el corte: [[posicion, department_id, area, pct], ...]. Solo trabajadores activos. */
    public static function rankingAreas(int $companyId, Carbon $desde, Carbon $hasta): array
    {
        $filas = self::consultaBase($companyId, $desde, $hasta)
            ->groupBy('e.department_id')
            ->selectRaw('e.department_id as id, MAX(COALESCE(d.nombre, "Sin área")) as area, ' . self::sqlPct() . ' as pct')
            ->orderByDesc('pct')
            ->get()
            ->filter(fn ($f) => $f->pct !== null)
            ->values();

        return $filas->map(fn ($f) => [
            'posicion' => 1 + $filas->filter(fn ($o) => (float) $o->pct > (float) $f->pct)->count(),
            'id'       => $f->id,
            'area'     => $f->area,
            'pct'      => (float) $f->pct,
        ])->all();
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
