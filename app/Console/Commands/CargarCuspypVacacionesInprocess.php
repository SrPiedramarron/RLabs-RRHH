<?php

namespace App\Console\Commands;

use App\Models\Employee;
use App\Models\VacacionHistorial;
use Illuminate\Console\Command;

/**
 * Carga única (oct. 2026): CUSPP de la declaración AFP y total de vacaciones
 * tomadas del control por periodo que mantiene RRHH de InProcess.
 * Sin --aplicar solo muestra lo que haría.
 */
class CargarCuspypVacacionesInprocess extends Command
{
    protected $signature = 'rrhh:cargar-cuspp-vacaciones {--aplicar : Guardar los cambios (sin esto solo simula)} {--company=2} {--archivo=storage/app/import/inprocess_cuspp_vacaciones_2026_10.json}';
    protected $description = 'Carga CUSPP y histórico de vacaciones de InProcess desde un JSON local, no versionado (carga única)';

    private const CORTE = '2026-10-05';

    public function handle(): int
    {
        $aplicar = (bool) $this->option('aplicar');
        $company = (int) $this->option('company');
        $data    = json_decode(file_get_contents(base_path($this->option('archivo'))), true);

        $empleados = Employee::withoutGlobalScopes()->where('company_id', $company)->get()->keyBy(fn ($e) => ltrim((string) $e->dni, '0'));
        $clave     = fn ($dni) => ltrim((string) $dni, '0');

        $this->info($aplicar ? '== APLICANDO ==' : '== SIMULACIÓN (agrega --aplicar para guardar) ==');

        $this->line('--- CUSPP ---');
        $nuevos = $iguales = 0;
        foreach ($data['afp'] as [$dni, $cuspp, $nombre]) {
            $e = $empleados[$clave($dni)] ?? null;
            if (! $e) {
                $this->warn("Sin trabajador en la empresa: $dni $nombre");
                continue;
            }
            if ($e->cuspp === $cuspp) {
                $iguales++;
                continue;
            }
            if ($e->cuspp) {
                $this->warn("{$e->apellidos}: tenía {$e->cuspp}, el Excel trae $cuspp (se reemplaza)");
            }
            if ($aplicar) {
                $e->forceFill(['cuspp' => $cuspp])->save();
            }
            $nuevos++;
        }
        $this->line("CUSPP: $nuevos por cargar/actualizar, $iguales ya estaban iguales.");

        $this->line('--- VACACIONES (días tomados hasta ' . self::CORTE . ') ---');
        $rows = [];
        foreach ($data['vac'] as [$dni, $nombre, $tomados]) {
            $e = $empleados[$clave($dni)] ?? null;
            if (! $e) {
                $this->warn("Sin trabajador en la empresa: $dni $nombre");
                continue;
            }
            $sistema = (int) VacacionHistorial::where('employee_id', $e->id)
                ->where('fecha_inicio', '<=', self::CORTE)->sum('dias');
            $dif = (int) round($tomados) - $sistema;
            $accion = $dif > 0 ? "agrega +$dif" : ($dif < 0 ? 'REVISAR (sistema tiene más)' : 'ok');
            $rows[] = [$e->id, $e->apellidos, $sistema, (int) $tomados, $dif, $accion];

            if ($aplicar && $dif > 0 && $e->fecha_ingreso) {
                // Ajuste de saldo histórico: se fecha en el ingreso (1 día de rango)
                // para que no cuente como vacaciones de ningún mes en PLAME/jornada.
                VacacionHistorial::create([
                    'employee_id'  => $e->id,
                    'fecha_inicio' => $e->fecha_ingreso->toDateString(),
                    'fecha_fin'    => $e->fecha_ingreso->toDateString(),
                    'dias'         => $dif,
                    'observacion'  => 'Ajuste histórico según control de vacaciones de RRHH (oct-2026)',
                ]);
            }
        }
        $this->table(['id', 'Trabajador', 'Sistema', 'Excel', 'Dif', 'Acción'], $rows);

        return self::SUCCESS;
    }
}
