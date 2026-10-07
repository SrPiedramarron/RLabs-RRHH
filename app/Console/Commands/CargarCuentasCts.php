<?php

namespace App\Console\Commands;

use App\Models\Employee;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

/**
 * Carga única (oct. 2026): banco y número de cuenta de CTS de cada trabajador,
 * tomados de las hojas "CUENTAS" de RRHH. Busca por DNI y, si no hay DNI, por
 * nombre. Lee un JSON local no versionado. Sin --aplicar solo simula.
 */
class CargarCuentasCts extends Command
{
    protected $signature = 'rrhh:cargar-cuentas-cts {--aplicar : Guardar (sin esto solo simula)} {--archivo=storage/app/import/cuentas_cts_2026_10.json}';
    protected $description = 'Carga banco y número de cuenta CTS desde un JSON (carga única)';

    public function handle(): int
    {
        $aplicar = (bool) $this->option('aplicar');
        $filas   = json_decode(file_get_contents(base_path($this->option('archivo'))), true);
        $this->info($aplicar ? '== APLICANDO ==' : '== SIMULACIÓN (agrega --aplicar para guardar) ==');

        $empleados = Employee::withoutGlobalScopes()->get();
        $tabla = [];

        foreach ($filas as $f) {
            $e = $this->buscar($empleados, (int) $f['company'], $f['dni'] ?? null, $f['nombre'] ?? '');
            if (! $e) {
                $this->warn("Sin coincidencia única: " . ($f['nombre'] ?: $f['dni']) . " (empresa {$f['company']})");
                continue;
            }
            if (! $f['cuenta']) {
                $this->warn("Sin número de cuenta en el Excel: {$e->apellidos}");
                continue;
            }

            $igual = $e->numero_cuenta_cts === $f['cuenta'] && $e->banco_cts === $f['banco'];
            $nota  = $igual ? 'ya estaba' : ($e->numero_cuenta_cts && $e->numero_cuenta_cts !== $f['cuenta'] ? "REEMPLAZA {$e->numero_cuenta_cts}" : 'nueva');
            $tabla[] = [$e->id, $e->apellidos . ', ' . $e->nombres, $f['banco'], $f['cuenta'], $nota];

            if ($aplicar && ! $igual) {
                $e->forceFill(['banco_cts' => $f['banco'], 'numero_cuenta_cts' => $f['cuenta']])->save();
            }
        }

        $this->table(['id', 'Trabajador', 'Banco', 'Cuenta CTS', 'Estado'], $tabla);

        return self::SUCCESS;
    }

    private function buscar($empleados, int $company, ?string $dni, string $nombre): ?Employee
    {
        $limpiar = fn ($s) => ltrim(preg_replace('/\D/', '', (string) $s), '0');

        if ($dni && $limpiar($dni) !== '') {
            $m = $empleados->filter(fn ($e) => $e->company_id === $company && $limpiar($e->dni) === $limpiar($dni));
            if ($m->count() === 1) {
                return $m->first();
            }
        }

        if ($nombre === '') {
            return null;
        }

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
