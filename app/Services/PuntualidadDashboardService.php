<?php

namespace App\Services;

use App\Support\EstadisticasAsistencia as Est;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Datos del dashboard de tardanzas y puntualidad: por persona, por área, por sede, por día y
 * por día de la semana. Definiciones (las mismas del ranking y de Beneficios):
 *   puntual = día presente sin minutos de tardanza; tarde = día con tardanza;
 *   % puntualidad = puntuales ÷ (puntuales + tardes).
 * Las ausencias injustificadas se muestran aparte. Se excluye al personal exonerado de registro
 * (no sujeto a fiscalización).
 */
class PuntualidadDashboardService
{
    public function build(?int $companyId, Carbon $desde, Carbon $hasta, ?int $locationId = null, ?int $departmentId = null): array
    {
        $base = fn () => DB::table('attendance_records as a')
            ->join('employees as e', 'e.id', '=', 'a.employee_id')
            ->leftJoin('locations as l', 'l.id', '=', 'e.location_id')
            ->leftJoin('departments as d', 'd.id', '=', 'e.department_id')
            ->where('e.exonerado_registro', false)
            ->when($companyId, fn ($q) => $q->where('a.company_id', $companyId))
            ->when($locationId, fn ($q) => $q->where('e.location_id', $locationId))
            ->when($departmentId, fn ($q) => $q->where('e.department_id', $departmentId))
            ->whereBetween('a.fecha', [$desde->toDateString(), $hasta->toDateString()]);

        $medidas = Est::sqlPuntual() . ' as p, ' . Est::sqlTarde() . ' as t, '
            . "COALESCE(SUM(CASE WHEN a.estado <> 'ausente' AND a.minutos_tarde > 0 THEN a.minutos_tarde ELSE 0 END), 0) as min, "
            . "COALESCE(SUM(CASE WHEN a.estado = 'ausente' AND a.justificado = 0 THEN 1 ELSE 0 END), 0) as aus";

        $fila = fn ($r, array $extra = []) => array_merge($extra, [
            'p'   => (int) $r->p,
            't'   => (int) $r->t,
            'min' => (int) $r->min,
            'aus' => (int) $r->aus,
            'pct' => ($r->p + $r->t) > 0 ? round(100 * $r->p / ($r->p + $r->t), 1) : null,
        ]);

        $personas = $base()->groupBy('e.id')
            ->selectRaw("e.id, MAX(CONCAT(e.apellidos, ', ', e.nombres)) as n, MAX(COALESCE(l.nombre, 'Sin sede')) as sede, MAX(COALESCE(d.nombre, 'Sin área')) as area, " . $medidas)
            ->get()->map(fn ($r) => $fila($r, ['id' => (int) $r->id, 'n' => $r->n, 'sede' => $r->sede, 'area' => $r->area]))->all();

        $areas = $base()->groupBy('e.department_id')
            ->selectRaw("e.department_id as id, MAX(COALESCE(d.nombre, 'Sin área')) as n, COUNT(DISTINCT e.id) as personas, " . $medidas)
            ->get()->map(fn ($r) => $fila($r, ['id' => (int) $r->id, 'n' => $r->n, 'personas' => (int) $r->personas]))->all();

        $sedes = $base()->groupBy('e.location_id')
            ->selectRaw("e.location_id as id, MAX(COALESCE(l.nombre, 'Sin sede')) as n, COUNT(DISTINCT e.id) as personas, " . $medidas)
            ->get()->map(fn ($r) => $fila($r, ['id' => (int) $r->id, 'n' => $r->n, 'personas' => (int) $r->personas]))->all();

        $matriz = $base()->groupBy('e.location_id', 'e.department_id')
            ->selectRaw("MAX(COALESCE(l.nombre, 'Sin sede')) as sede, MAX(COALESCE(d.nombre, 'Sin área')) as area, " . $medidas)
            ->get()->map(fn ($r) => $fila($r, ['sede' => $r->sede, 'area' => $r->area]))->all();

        $dias = $base()->groupBy('a.fecha')->orderBy('a.fecha')
            ->selectRaw('a.fecha as f, ' . $medidas)
            ->get()->map(fn ($r) => $fila($r, ['f' => (string) $r->f]))->all();

        $semana = $base()->groupByRaw('WEEKDAY(a.fecha)')->orderByRaw('WEEKDAY(a.fecha)')
            ->selectRaw('WEEKDAY(a.fecha) as d, ' . $medidas)
            ->get()->map(fn ($r) => $fila($r, ['d' => (int) $r->d]))->all(); // 0 = lunes … 6 = domingo

        $p = array_sum(array_column($personas, 'p'));
        $t = array_sum(array_column($personas, 't'));

        return [
            'periodo' => [
                'desde' => $desde->toDateString(),
                'hasta' => $hasta->toDateString(),
            ],
            'umbral'   => Est::umbralBeneficio(),
            'kpi' => [
                'personas'      => count($personas),
                'p'             => $p,
                't'             => $t,
                'pct'           => ($p + $t) > 0 ? round(100 * $p / ($p + $t), 1) : null,
                'min'           => array_sum(array_column($personas, 'min')),
                'aus'           => array_sum(array_column($personas, 'aus')),
                'con_tardanza'  => count(array_filter($personas, fn ($x) => $x['t'] > 0)),
                'sin_tardanza'  => count(array_filter($personas, fn ($x) => $x['t'] === 0 && $x['p'] > 0)),
            ],
            'personas' => $personas,
            'areas'    => $areas,
            'sedes'    => $sedes,
            'matriz'   => $matriz,
            'dias'     => $dias,
            'semana'   => $semana,
        ];
    }
}
