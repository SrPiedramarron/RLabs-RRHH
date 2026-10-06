<?php

namespace App\Console\Commands;

use App\Models\CtsInsumoHistorico;
use App\Models\Employee;
use App\Models\Gratificacion;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

/**
 * Carga única (oct. 2026): gratificación de julio ya pagada fuera del sistema
 * y montos mensuales (comisiones, bonos, horas extra) que alimentan la CTS de
 * noviembre. Lee un JSON local no versionado. Sin --aplicar solo simula.
 */
class CargarInsumosCts extends Command
{
    protected $signature = 'rrhh:cargar-insumos-cts {--aplicar : Guardar (sin esto solo simula)} {--archivo=storage/app/import/cts_insumos_2026_10.json}';
    protected $description = 'Carga gratificación de julio y comisiones/bonos/HE históricos para la CTS (carga única)';

    public function handle(): int
    {
        $aplicar = (bool) $this->option('aplicar');
        $data    = json_decode(file_get_contents(base_path($this->option('archivo'))), true);
        $this->info($aplicar ? '== APLICANDO ==' : '== SIMULACIÓN (agrega --aplicar para guardar) ==');

        $empleados = Employee::withoutGlobalScopes()->get();

        $this->line('--- GRATIFICACIÓN JULIO 2026 ---');
        $filas = [];
        foreach ($data['grati'] as $g) {
            if ((float) $g['monto'] <= 0) {
                continue;
            }
            $e = $this->buscar($empleados, (int) $g['company'], $g['nombre']);
            if (! $e) {
                $this->warn("Sin coincidencia única: {$g['nombre']} (empresa {$g['company']})");
                continue;
            }
            $existente = Gratificacion::where('employee_id', $e->id)->where('periodo', '2026-07')->first();
            $filas[] = [$e->id, $e->apellidos, $g['monto'], $g['bonif'], $existente ? 'ya existe: ' . $existente->monto_gratificacion . ' (se actualiza)' : 'nueva'];

            if ($aplicar) {
                Gratificacion::updateOrCreate(
                    ['employee_id' => $e->id, 'periodo' => '2026-07'],
                    [
                        'company_id'                  => $e->company_id,
                        'tipo'                        => 'julio',
                        'anio'                        => 2026,
                        'nombres'                     => $e->nombres,
                        'apellidos'                   => $e->apellidos,
                        'dni'                         => $e->dni,
                        'cargo'                       => $e->cargo,
                        'sueldo_base'                 => $g['sueldo'],
                        'asignacion_familiar'         => $g['asig'],
                        'meses_computables'           => $g['meses'],
                        'remuneracion_computable'     => $g['monto'],
                        'monto_gratificacion'         => $g['monto'],
                        'bonificacion_extraordinaria' => $g['bonif'],
                        'monto_total'                 => round($g['monto'] + $g['bonif'], 2),
                        'calculado_at'                => now(),
                    ]
                );
            }
        }
        $this->table(['id', 'Trabajador', 'Grati', 'Bonif 9%', 'Estado'], $filas);

        $this->line('--- INSUMOS MENSUALES ---');
        $filas = [];
        foreach ($data['insumos'] as $i) {
            $e = $this->buscar($empleados, (int) $i['company'], $i['nombre']);
            if (! $e) {
                $this->warn("Sin coincidencia única: {$i['nombre']} (empresa {$i['company']})");
                continue;
            }
            $filas[] = [$e->id, $e->apellidos, $i['periodo'], $i['concepto'], $i['monto']];
            if ($aplicar) {
                CtsInsumoHistorico::updateOrCreate(
                    ['employee_id' => $e->id, 'periodo' => $i['periodo'], 'concepto' => $i['concepto']],
                    ['monto' => $i['monto'], 'nota' => 'Carga desde Excel de CTS (oct-2026)']
                );
            }
        }
        $this->table(['id', 'Trabajador', 'Periodo', 'Concepto', 'Monto'], $filas);

        return self::SUCCESS;
    }

    /** Busca por conjunto de palabras (apellidos+nombres en cualquier orden), solo si hay una coincidencia. */
    private function buscar($empleados, int $company, string $nombre): ?Employee
    {
        $clave = fn (string $s) => collect(preg_split('/\s+/', Str::upper(Str::ascii($s)), -1, PREG_SPLIT_NO_EMPTY))->sort()->values()->all();
        $buscado = $clave($nombre);

        $m = $empleados->filter(fn ($e) => $e->company_id === $company
            && $clave($e->apellidos . ' ' . $e->nombres) === $buscado);

        return $m->count() === 1 ? $m->first() : null;
    }
}
