<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

/** Revisa que nada se cruce entre empresas. Lo usan el comando rrhh:auditar-cruces-empresa y el recordatorio semanal. */
class AuditoriaCrucesService
{
    /** @return array{0: array, 1: int} filas de la tabla y total de ERRORES */
    public function revisar(): array
    {
        $filas = [];
        $total = 0;

        $anotar = function (string $titulo, $rows, string $nivel = 'ERROR') use (&$filas, &$total) {
            $n = count($rows);
            $total += $nivel === 'ERROR' ? $n : 0;
            $filas[] = [$n === 0 ? 'OK' : $nivel, $titulo, $n, collect($rows)->take(3)->implode(' · ')];
        };

        // 1) Ficha del trabajador contra su empresa
        foreach ([['departments', 'department_id', 'Área'], ['locations', 'location_id', 'Sede'], ['schedules', 'schedule_id', 'Horario principal']] as [$tabla, $col, $et]) {
            $anotar("Ficha: {$et} de otra empresa", DB::table('employees as e')
                ->join("{$tabla} as x", 'x.id', '=', "e.{$col}")
                ->whereColumn('x.company_id', '<>', 'e.company_id')
                ->selectRaw("CONCAT(e.apellidos, ' ', e.nombres, ' → ', x.nombre) as d")->pluck('d'));
        }
        $anotar('Ficha: horarios adicionales de otra empresa', DB::table('employee_schedules as p')
            ->join('employees as e', 'e.id', '=', 'p.employee_id')
            ->join('schedules as s', 's.id', '=', 'p.schedule_id')
            ->whereColumn('s.company_id', '<>', 'e.company_id')
            ->selectRaw("CONCAT(e.apellidos, ' ', e.nombres, ' → ', s.nombre) as d")->pluck('d'));

        // 2) Cualquier tabla con employee_id + company_id: la empresa debe ser la del trabajador
        $tablas = DB::table('information_schema.columns')
            ->where('table_schema', DB::getDatabaseName())
            ->whereIn('column_name', ['employee_id', 'company_id'])
            ->groupBy('table_name')->havingRaw('COUNT(DISTINCT column_name) = 2')
            ->selectRaw('table_name as t')
            ->pluck('t')->reject(fn ($t) => $t === 'employees')->sort()->values();
        foreach ($tablas as $t) {
            $anotar("Registros de {$t} con empresa distinta a la del trabajador", DB::table("{$t} as r")
                ->join('employees as e', 'e.id', '=', 'r.employee_id')
                ->whereColumn('r.company_id', '<>', 'e.company_id')
                ->selectRaw("CONCAT(e.apellidos, ' ', e.nombres, ' (ficha ', e.company_id, ', registro ', r.company_id, ')') as d")
                ->limit(500)->pluck('d')->unique()->values());
        }

        // 3) Sedes de las marcaciones y sedes adicionales (puede ser legítimo si comparten oficina)
        $anotar('Marcaciones en una sede de otra empresa (informativo: oficinas compartidas)', DB::table('attendance_records as a')
            ->join('locations as l', 'l.id', '=', 'a.location_id')
            ->whereColumn('l.company_id', '<>', 'a.company_id')
            ->selectRaw("CONCAT('sede ', l.nombre, ' (empresa ', l.company_id, ') con marcación de la empresa ', a.company_id) as d")->limit(500)->pluck('d')->unique()->values(), 'AVISO');
        $anotar('Sedes adicionales de otra empresa (informativo)', DB::table('employee_devices as d')
            ->join('employees as e', 'e.id', '=', 'd.employee_id')
            ->join('locations as l', 'l.id', '=', 'd.location_id')
            ->whereColumn('l.company_id', '<>', 'e.company_id')
            ->selectRaw("CONCAT(e.apellidos, ' ', e.nombres, ' → ', l.nombre) as d")->pluck('d'), 'AVISO');

        // 4) Repetidos dentro de la misma empresa (dos «Ventas» en Quantum)
        foreach (['departments' => 'Áreas', 'locations' => 'Sedes', 'schedules' => 'Horarios'] as $tabla => $et) {
            $anotar("{$et} repetidas dentro de una misma empresa", DB::table($tabla)
                ->groupBy('company_id', DB::raw('UPPER(TRIM(nombre))'))->havingRaw('COUNT(*) > 1')
                ->selectRaw("CONCAT(MIN(nombre), ' ×', COUNT(*), ' (empresa ', company_id, ')') as d")->pluck('d'), 'AVISO');
        }

        // 5) Usuarios del panel sin empresa (ven todas las empresas; debe ser solo super_admin)
        $anotar('Usuarios del panel sin empresa que no son super_admin', DB::table('users as u')
            ->whereNull('u.company_id')
            ->whereNotExists(fn ($q) => $q->select(DB::raw(1))->from('model_has_roles as m')->join('roles as r', 'r.id', '=', 'm.role_id')
                ->whereColumn('m.model_id', 'u.id')->where('m.model_type', 'App\\Models\\User')->where('r.name', 'super_admin'))
            ->pluck('u.email'), 'AVISO');

        return [$filas, $total];
    }
}
