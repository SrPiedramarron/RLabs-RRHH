<?php

namespace App\Console\Commands;

use App\Models\Employee;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

/**
 * Carga única (oct. 2026): jefe directo de cada trabajador según el árbol
 * jerárquico entregado por RRHH. Lee un JSON local no versionado.
 * Sin --aplicar solo simula.
 */
class CargarJerarquia extends Command
{
    protected $signature = 'rrhh:cargar-jerarquia {--aplicar : Guardar (sin esto solo simula)} {--archivo=storage/app/import/jerarquia_2026_10.json}';
    protected $description = 'Asigna el jefe directo de cada trabajador desde un JSON (carga única)';

    public function handle(): int
    {
        $aplicar = (bool) $this->option('aplicar');
        $filas   = json_decode(file_get_contents(base_path($this->option('archivo'))), true);
        $this->info($aplicar ? '== APLICANDO ==' : '== SIMULACIÓN (agrega --aplicar para guardar) ==');

        $empleados = Employee::withoutGlobalScopes()->where('active', true)->get();
        $tabla = [];

        foreach ($filas as $f) {
            $e = $this->buscar($empleados, (int) $f['company'], $f['nombre']);
            if (! $e) {
                $this->warn("Sin coincidencia única: {$f['nombre']} (empresa {$f['company']})");
                continue;
            }

            $jefe = null;
            if ($f['jefe']) {
                $jefe = $this->buscar($empleados, (int) $f['company'], $f['jefe']);
                if (! $jefe) {
                    $this->warn("Jefe sin coincidencia única: {$f['jefe']} (de {$f['nombre']})");
                    continue;
                }
                if ($jefe->id === $e->id) {
                    continue;
                }
            }

            $tabla[] = [$e->id, $e->apellidos . ', ' . $e->nombres, $jefe ? $jefe->apellidos . ', ' . $jefe->nombres : '(sin jefe)'];
            if ($aplicar) {
                $e->forceFill(['jefe_directo_id' => $jefe?->id])->save();
            }
        }

        $this->table(['id', 'Trabajador', 'Jefe directo'], $tabla);

        return self::SUCCESS;
    }

    /**
     * Busca por palabras: todas las del nombre dado deben estar entre las del
     * trabajador (apellidos + nombres, en cualquier orden), tolerando 1 letra
     * de diferencia en palabras largas. Solo devuelve si hay una única coincidencia.
     */
    private function buscar($empleados, int $company, string $nombre): ?Employee
    {
        $tokens = fn (string $s) => collect(preg_split('/\s+/', Str::upper(Str::ascii($s)), -1, PREG_SPLIT_NO_EMPTY));
        $buscado = $tokens($nombre);

        $m = $empleados->filter(function ($e) use ($company, $buscado, $tokens) {
            if ($e->company_id !== $company) {
                return false;
            }
            $propios = $tokens($e->apellidos . ' ' . $e->nombres);

            return $buscado->every(fn ($t) => $propios->contains(
                fn ($p) => $p === $t || (strlen($t) >= 5 && levenshtein($p, $t) <= 1)
            ));
        });

        return $m->count() === 1 ? $m->first() : null;
    }
}
