<?php

namespace App\Console\Commands;

use App\Models\Employee;
use Illuminate\Console\Command;

/**
 * Compara la fecha de ingreso de cada ficha con la que usa RRHH en su control
 * de vacaciones y muestra las diferencias. Con --aplicar corrige las fichas.
 * Lee un JSON local no versionado.
 */
class CompararIngresos extends Command
{
    protected $signature = 'rrhh:comparar-ingresos {--aplicar : Corregir las fichas con la fecha del Excel} {--archivo=storage/app/import/ingresos_2026_10.json}';
    protected $description = 'Compara las fechas de ingreso de las fichas con las de un JSON (opcionalmente las corrige)';

    public function handle(): int
    {
        $aplicar = (bool) $this->option('aplicar');
        $filas   = json_decode(file_get_contents(base_path($this->option('archivo'))), true);
        $this->info($aplicar ? '== APLICANDO ==' : '== SOLO COMPARACIÓN (agrega --aplicar para corregir) ==');

        $limpiar = fn ($s) => ltrim(preg_replace('/\D/', '', (string) $s), '0');
        $tabla = [];
        $iguales = 0;

        foreach ($filas as $f) {
            $e = Employee::withoutGlobalScopes()->where('company_id', (int) $f['company'])->get()
                ->first(fn ($x) => $limpiar($x->dni) === $limpiar($f['dni']));

            if (! $e) {
                $this->warn("Sin trabajador: {$f['dni']} {$f['nombre']}");
                continue;
            }

            $actual = $e->fecha_ingreso?->toDateString();
            if ($actual === $f['ingreso']) {
                $iguales++;
                continue;
            }

            $tabla[] = [$e->id, $e->apellidos . ', ' . $e->nombres, $actual ?? '—', $f['ingreso']];
            if ($aplicar) {
                $e->forceFill(['fecha_ingreso' => $f['ingreso']])->save();
            }
        }

        $this->table(['id', 'Trabajador', 'Ficha (sistema)', 'Excel de RRHH'], $tabla);
        $this->line("Iguales: $iguales | Distintas: " . count($tabla));

        return self::SUCCESS;
    }
}
