<?php

namespace App\Console\Commands;

use App\Models\Employee;
use App\Models\VacacionHistorial;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

/**
 * Carga única (oct. 2026): histórico de vacaciones (días tomados) y fecha de
 * ingreso de una empresa, desde el control por periodo de RRHH. Busca por
 * nombre. Lee un JSON local no versionado. Sin --aplicar solo simula.
 *
 * Si el Excel trae más días que el sistema, agrega UN ajuste por la diferencia
 * (fechado en el ingreso, 1 día de rango, para que no cuente como vacaciones de
 * ningún mes en PLAME ni en la jornada). Si el sistema trae más, solo avisa.
 */
class CargarVacacionesHistorico extends Command
{
    protected $signature = 'rrhh:cargar-vacaciones-historico {--aplicar : Guardar (sin esto solo simula)} {--archivo=storage/app/import/vacaciones_quantum_2026_10.json} {--corte=2026-09-24 : Fecha de corte del control}';
    protected $description = 'Carga días tomados y fecha de ingreso desde un JSON por nombre (carga única)';

    public function handle(): int
    {
        $aplicar = (bool) $this->option('aplicar');
        $corte   = $this->option('corte');
        $filas   = json_decode(file_get_contents(base_path($this->option('archivo'))), true);
        $this->info($aplicar ? '== APLICANDO ==' : '== SIMULACIÓN (agrega --aplicar para guardar) ==');

        $empleados = Employee::withoutGlobalScopes()->get();
        $tabla = [];

        foreach ($filas as $f) {
            $e = $this->buscar($empleados, (int) $f['company'], $f['nombre']);
            if (! $e) {
                $this->warn("Sin coincidencia única: {$f['nombre']} (empresa {$f['company']})");
                continue;
            }

            $sistema = (int) VacacionHistorial::where('employee_id', $e->id)->where('fecha_inicio', '<=', $corte)->sum('dias');
            $dif     = (int) round($f['tomados']) - $sistema;
            $accion  = $dif > 0 ? "agrega +$dif" : ($dif < 0 ? 'REVISAR (sistema tiene más)' : 'ok');
            $ingresoActual = $e->fecha_ingreso?->toDateString();
            $cambiaIngreso = $ingresoActual !== $f['ingreso'];

            $tabla[] = [$e->id, $e->apellidos, $sistema, (int) $f['tomados'], $accion, $cambiaIngreso ? "$ingresoActual → {$f['ingreso']}" : 'ok'];

            if ($aplicar) {
                if ($cambiaIngreso) {
                    $e->forceFill(['fecha_ingreso' => $f['ingreso']])->save();
                }
                if ($dif > 0) {
                    VacacionHistorial::create([
                        'employee_id'  => $e->id,
                        'fecha_inicio' => $f['ingreso'],
                        'fecha_fin'    => $f['ingreso'],
                        'dias'         => $dif,
                        'observacion'  => 'Ajuste histórico según control de vacaciones de RRHH (oct-2026)',
                    ]);
                    $e->forceFill(['dias_tomados' => (float) $e->dias_tomados + $dif])->save();
                }
            }
        }

        $this->table(['id', 'Trabajador', 'Sistema', 'Excel', 'Días', 'Fecha de ingreso'], $tabla);

        return self::SUCCESS;
    }

    /** Todas las palabras del nombre dado deben estar entre las del trabajador (tolera 1 letra de diferencia). */
    private function buscar($empleados, int $company, string $nombre): ?Employee
    {
        $tokens  = fn (string $s) => collect(preg_split('/\s+/', Str::upper(Str::ascii($s)), -1, PREG_SPLIT_NO_EMPTY));
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
