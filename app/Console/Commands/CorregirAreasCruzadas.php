<?php

namespace App\Console\Commands;

use App\Models\Department;
use App\Models\Employee;
use App\Models\Location;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Trabajadores cuya área o sede es de OTRA empresa (pasaba porque la ficha ofrecía las de todas las
 * empresas). Los pasa al área/sede del mismo nombre de SU empresa; si esa no existe, la crea con
 * --crear. Simula por defecto.
 */
class CorregirAreasCruzadas extends Command
{
    protected $signature = 'rrhh:corregir-areas-cruzadas {--aplicar : Guardar (sin esto solo simula)} {--crear : Crear en la empresa del trabajador las áreas/sedes que no existan}';
    protected $description = 'Reasigna a su propia empresa las áreas y sedes que quedaron cruzadas entre empresas';

    private function clave(string $s): string
    {
        return Str::upper(Str::ascii(trim($s)));
    }

    /**
     * Un horario de otra empresa: si TODOS los que lo usan son de una sola empresa, el horario se mueve a esa
     * empresa; si lo usan de varias, se pasa cada trabajador al horario del mismo nombre de su empresa, o (con
     * --crear) a una copia del horario creada en su empresa.
     */
    private function horarios(bool $crear): array
    {
        $filas = [];

        // Pares (trabajador, horario) cruzados: principal y adicionales
        $cruces = collect();
        foreach (Employee::withoutGlobalScopes()->join('schedules as s', 's.id', '=', 'employees.schedule_id')
            ->whereColumn('s.company_id', '<>', 'employees.company_id')->select('employees.id as emp', 'employees.schedule_id as sch', DB::raw("'Horario principal' as tipo"))->get() as $r) {
            $cruces->push($r);
        }
        foreach (DB::table('employee_schedules as p')->join('employees as e', 'e.id', '=', 'p.employee_id')->join('schedules as s', 's.id', '=', 'p.schedule_id')
            ->whereColumn('s.company_id', '<>', 'e.company_id')->select('e.id as emp', 'p.schedule_id as sch', DB::raw("'Horario adicional' as tipo"))->get() as $r) {
            $cruces->push($r);
        }

        foreach ($cruces->groupBy('sch') as $schId => $pares) {
            $sch = \App\Models\Schedule::withoutGlobalScopes()->find($schId);
            if (! $sch) {
                continue;
            }

            $usuarios = Employee::withoutGlobalScopes()->where('schedule_id', $schId)->pluck('company_id')
                ->merge(DB::table('employee_schedules as p')->join('employees as e', 'e.id', '=', 'p.employee_id')->where('p.schedule_id', $schId)->pluck('e.company_id'))
                ->unique()->values();

            if ($usuarios->count() === 1) {
                $empresa = (int) $usuarios->first();
                $antes   = $sch->company_id;
                $sch->update(['company_id' => $empresa]);
                $filas[] = ['Horario', "{$sch->nombre} ({$pares->count()} trabajador(es))", $empresa, "empresa {$antes}", "mueve el horario a la empresa {$empresa}"];
                continue;
            }

            foreach ($pares as $par) {
                $e = Employee::withoutGlobalScopes()->find($par->emp);
                $propio = \App\Models\Schedule::withoutGlobalScopes()->where('company_id', $e->company_id)->get()
                    ->first(fn ($x) => $this->clave($x->nombre) === $this->clave($sch->nombre));
                $accion = 'reasigna a la existente';

                if (! $propio) {
                    if (! $crear) {
                        $filas[] = [$par->tipo, "{$e->apellidos} {$e->nombres}", $e->company_id, "{$sch->nombre} (emp. {$sch->company_id})", '— no existe en su empresa (usa --crear)'];
                        continue;
                    }
                    $propio = $sch->replicate();
                    $propio->company_id = $e->company_id;
                    $propio->save();
                    $accion = 'crea una copia y reasigna';
                }

                if ($par->tipo === 'Horario principal') {
                    Employee::withoutGlobalScopes()->where('id', $e->id)->update(['schedule_id' => $propio->id]);
                } else {
                    DB::table('employee_schedules')->where('employee_id', $e->id)->where('schedule_id', $schId)->update(['schedule_id' => $propio->id]);
                }
                $filas[] = [$par->tipo, "{$e->apellidos} {$e->nombres}", $e->company_id, "{$sch->nombre} (emp. {$sch->company_id})", "{$accion}: {$propio->nombre} (id {$propio->id})"];
            }
        }

        return $filas;
    }

    public function handle(): int
    {
        $aplicar = (bool) $this->option('aplicar');
        $crear   = (bool) $this->option('crear');
        $this->info($aplicar ? '== APLICANDO ==' : '== SIMULACIÓN (agrega --aplicar para guardar) ==');

        $tabla = [];
        DB::beginTransaction();

        foreach ([['department_id', Department::class, 'Área'], ['location_id', Location::class, 'Sede']] as [$campo, $modelo, $etiqueta]) {
            $tabla_tabla = (new $modelo)->getTable();
            $cruzados = Employee::withoutGlobalScopes()
                ->join("{$tabla_tabla} as x", 'x.id', '=', "employees.{$campo}")
                ->whereColumn('x.company_id', '<>', 'employees.company_id')
                ->select('employees.*', 'x.nombre as nombre_actual', 'x.company_id as empresa_actual')
                ->get();

            foreach ($cruzados as $e) {
                $propias = $modelo::withoutGlobalScopes()->where('company_id', $e->company_id)->get();
                $destino = $propias->first(fn ($x) => $this->clave($x->nombre) === $this->clave($e->nombre_actual));
                $accion  = 'reasigna a la existente';

                if (! $destino) {
                    if (! $crear) {
                        $tabla[] = [$etiqueta, "{$e->apellidos} {$e->nombres}", $e->company_id, "{$e->nombre_actual} (emp. {$e->empresa_actual})", '— no existe en su empresa (usa --crear)'];
                        continue;
                    }
                    $destino = $modelo::create(['company_id' => $e->company_id, 'nombre' => $e->nombre_actual]);
                    $accion  = 'crea y reasigna';
                }

                Employee::withoutGlobalScopes()->where('id', $e->id)->update([$campo => $destino->id]);
                $tabla[] = [$etiqueta, "{$e->apellidos} {$e->nombres}", $e->company_id, "{$e->nombre_actual} (emp. {$e->empresa_actual})", "{$accion}: {$destino->nombre} (id {$destino->id})"];
            }
        }

        // ── Horarios (principal y adicionales) ──────────────────────────────────────
        $tabla = array_merge($tabla, $this->horarios($crear));

        $aplicar ? DB::commit() : DB::rollBack();

        $this->table(['Campo', 'Trabajador', 'Su empresa', 'Tenía', 'Acción'], $tabla);
        $this->line($tabla ? count($tabla) . ' registro(s).' : 'No hay áreas, sedes ni horarios cruzados.');

        return self::SUCCESS;
    }
}
