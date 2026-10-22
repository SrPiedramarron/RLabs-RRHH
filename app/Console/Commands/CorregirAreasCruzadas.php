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

        $aplicar ? DB::commit() : DB::rollBack();

        $this->table(['Campo', 'Trabajador', 'Su empresa', 'Tenía', 'Acción'], $tabla);
        $this->line($tabla ? count($tabla) . ' registro(s).' : 'No hay áreas ni sedes cruzadas.');

        return self::SUCCESS;
    }
}
